<?php

declare(strict_types=1);

namespace App\Infrastructure\Activation\Controller;

use App\Application\Activation\Command\SendVerificationEmailCommand;
use App\Application\GiftCard\Command\ActivateCommand;
use App\Application\GiftCard\Port\GiftCardReadModelRepositoryInterface;
use App\Domain\GiftCard\ValueObject\CustomerEmail;
use App\Domain\GiftCard\ValueObject\VerificationCode;
use App\Domain\Tenant\Port\TenantQueryRepositoryInterface;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Infrastructure\Activation\Entity\ActivationRequest;
use App\Application\Activation\Port\CardAssignmentCheckerInterface;
use App\Infrastructure\Activation\Entity\CardAssignment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ActivationController extends AbstractController
{
    public function __construct(
        private readonly GiftCardReadModelRepositoryInterface $giftCardRepository,
        private readonly TenantQueryRepositoryInterface $tenantQueryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly HttpClientInterface $httpClient,
        private readonly CardAssignmentCheckerInterface $cardAssignmentChecker,
    ) {}

    #[Route('/activate', name: 'activation_form', methods: ['GET'])]
    public function form(Request $request): Response
    {
        $returnUrl = $request->query->getString('return_url', '');
        $callbackUrl = $request->query->getString('callback_url', '');

        return $this->render('activation/form.html.twig', [
            'return_url' => $returnUrl,
            'callback_url' => $callbackUrl,
        ]);
    }

    #[Route('/activate', name: 'activation_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        $email = trim($request->request->getString('email'));
        $cardNumber = strtoupper(trim($request->request->getString('card_number')));
        $pin = trim($request->request->getString('pin'));
        $returnUrl = $request->request->getString('return_url', '');
        $callbackUrl = $request->request->getString('callback_url', '');

        // Validate email
        try {
            CustomerEmail::fromString($email);
        } catch (\InvalidArgumentException) {
            $this->addFlash('error', 'activation.error_invalid_email');
            return $this->redirectToForm($returnUrl, $callbackUrl);
        }

        // Find card by number
        $readModel = $this->giftCardRepository->findByCardNumber($cardNumber);

        if ($readModel === null) {
            $this->addFlash('error', 'activation.error_card_not_found');
            return $this->redirectToForm($returnUrl, $callbackUrl, $email, $cardNumber);
        }

        // Check PIN
        if ($readModel->pin !== $pin) {
            $this->addFlash('error', 'activation.error_invalid_pin');
            return $this->redirectToForm($returnUrl, $callbackUrl, $email, $cardNumber);
        }

        // Check card is INACTIVE
        if (strtolower($readModel->status) !== 'inactive') {
            $this->addFlash('error', 'activation.error_card_not_inactive');
            return $this->redirectToForm($returnUrl, $callbackUrl, $email, $cardNumber);
        }

        // Check card is not already assigned
        if ($this->cardAssignmentChecker->isCardAssigned($readModel->id)) {
            $this->addFlash('error', 'activation.error_card_already_assigned');
            return $this->redirectToForm($returnUrl, $callbackUrl, $email, $cardNumber);
        }

        // Validate return_url against tenant's allowed domain
        if ($returnUrl !== '') {
            try {
                $tenant = $this->tenantQueryRepository->findById(TenantId::fromString($readModel->tenantId));
                if ($tenant->getAllowedRedirectDomain() !== null) {
                    $urlHost = parse_url($returnUrl, PHP_URL_HOST);
                    if ($urlHost === null || !str_ends_with($urlHost, $tenant->getAllowedRedirectDomain())) {
                        $this->addFlash('error', 'activation.error_invalid_return_url');
                        return $this->redirectToForm($returnUrl, $callbackUrl, $email, $cardNumber);
                    }
                }
            } catch (\Throwable) {
                // If tenant not found, skip domain validation
            }
        }

        // Generate verification code
        $verificationCode = VerificationCode::generate();

        // Create ActivationRequest
        $activationRequest = new ActivationRequest(
            cardNumber: $cardNumber,
            customerEmail: $email,
            verificationCode: $verificationCode->toString(),
            giftCardId: $readModel->id,
            tenantId: $readModel->tenantId,
            returnUrl: $returnUrl,
            callbackUrl: $callbackUrl ?: null,
        );

        $this->entityManager->persist($activationRequest);
        $this->entityManager->flush();

        // Dispatch async email
        $this->messageBus->dispatch(new SendVerificationEmailCommand(
            email: $email,
            verificationCode: $verificationCode->toString(),
        ));

        return $this->redirectToRoute('activation_verify', ['id' => $activationRequest->getId()]);
    }

    #[Route('/activate/{id}/verify', name: 'activation_verify', methods: ['GET'])]
    public function verifyForm(string $id): Response
    {
        $activationRequest = $this->findActivationRequest($id);
        if ($activationRequest === null) {
            return $this->renderError('activation.error_request_not_found');
        }

        if ($activationRequest->isExpired()) {
            $activationRequest->markExpired();
            $this->entityManager->flush();
            return $this->renderError('activation.error_code_expired');
        }

        return $this->render('activation/verify.html.twig', [
            'activation_id' => $activationRequest->getId(),
            'email' => $activationRequest->getCustomerEmail(),
        ]);
    }

    #[Route('/activate/{id}/verify', name: 'activation_verify_submit', methods: ['POST'])]
    public function verify(Request $request, string $id): Response
    {
        $activationRequest = $this->findActivationRequest($id);
        if ($activationRequest === null) {
            return $this->renderError('activation.error_request_not_found');
        }

        if ($activationRequest->isExpired()) {
            $activationRequest->markExpired();
            $this->entityManager->flush();
            return $this->renderError('activation.error_code_expired');
        }

        $code = trim($request->request->getString('code'));

        if ($code !== $activationRequest->getVerificationCode()) {
            $this->addFlash('error', 'activation.error_invalid_code');
            return $this->redirectToRoute('activation_verify', ['id' => $id]);
        }

        $activationRequest->verify();
        $this->entityManager->flush();

        return $this->redirectToRoute('activation_summary', ['id' => $id]);
    }

    #[Route('/activate/{id}/summary', name: 'activation_summary', methods: ['GET'])]
    public function summary(string $id): Response
    {
        $activationRequest = $this->findActivationRequest($id);
        if ($activationRequest === null) {
            return $this->renderError('activation.error_request_not_found');
        }

        if (!$activationRequest->isVerified()) {
            return $this->renderError('activation.error_not_verified');
        }

        $readModel = $this->giftCardRepository->findById($activationRequest->getGiftCardId());
        if ($readModel === null) {
            return $this->renderError('activation.error_card_not_found');
        }

        return $this->render('activation/summary.html.twig', [
            'activation_id' => $activationRequest->getId(),
            'card_number' => $activationRequest->getCardNumber(),
            'balance_amount' => $readModel->balanceAmount,
            'balance_currency' => $readModel->balanceCurrency,
            'expires_at' => $readModel->expiresAt,
            'email' => $activationRequest->getCustomerEmail(),
        ]);
    }

    #[Route('/activate/{id}/assign', name: 'activation_assign', methods: ['POST'])]
    public function assign(string $id): Response
    {
        $activationRequest = $this->findActivationRequest($id);
        if ($activationRequest === null) {
            return $this->renderError('activation.error_request_not_found');
        }

        if (!$activationRequest->isVerified()) {
            return $this->renderError('activation.error_not_verified');
        }

        // Guard against race condition — second check before writing
        if ($this->cardAssignmentChecker->isCardAssigned($activationRequest->getGiftCardId())) {
            return $this->renderError('activation.error_card_already_assigned');
        }

        // Activate the gift card
        $this->messageBus->dispatch(new ActivateCommand(
            id: $activationRequest->getGiftCardId(),
            activatedAt: (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'),
        ));

        // Create CardAssignment
        $assignment = new CardAssignment(
            giftCardId: $activationRequest->getGiftCardId(),
            tenantId: $activationRequest->getTenantId(),
            customerEmail: $activationRequest->getCustomerEmail(),
        );

        $this->entityManager->persist($assignment);

        $activationRequest->complete();
        $this->entityManager->flush();

        // Server-to-server callback
        $callbackUrl = $activationRequest->getCallbackUrl();
        if ($callbackUrl !== null && $callbackUrl !== '') {
            try {
                $this->httpClient->request('POST', $callbackUrl, [
                    'json' => [
                        'status' => 'activated',
                        'gift_card_id' => $activationRequest->getGiftCardId(),
                        'card_number' => $activationRequest->getCardNumber(),
                        'customer_email' => $activationRequest->getCustomerEmail(),
                        'assigned_at' => $assignment->getAssignedAt()->format('Y-m-d\TH:i:s.uP'),
                    ],
                ]);
            } catch (\Throwable) {
                // Callback failure should not block the activation
            }
        }

        // Redirect to tenant return URL
        $returnUrl = $activationRequest->getReturnUrl();
        if ($returnUrl !== '') {
            $separator = str_contains($returnUrl, '?') ? '&' : '?';
            return $this->redirect($returnUrl . $separator . http_build_query([
                'status' => 'success',
                'card_id' => $activationRequest->getGiftCardId(),
            ]));
        }

        // If no return URL, show success message
        $this->addFlash('success', 'activation.success_heading');
        return $this->redirectToRoute('activation_form');
    }

    private function findActivationRequest(string $id): ?ActivationRequest
    {
        return $this->entityManager->getRepository(ActivationRequest::class)->find($id);
    }

    private function renderError(string $messageKey): Response
    {
        return $this->render('activation/error.html.twig', [
            'error_message' => $messageKey,
        ]);
    }

    private function redirectToForm(string $returnUrl, string $callbackUrl, string $email = '', string $cardNumber = ''): Response
    {
        return $this->render('activation/form.html.twig', [
            'return_url' => $returnUrl,
            'callback_url' => $callbackUrl,
            'submitted_email' => $email,
            'submitted_card_number' => $cardNumber,
        ]);
    }
}
