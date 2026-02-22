<?php

declare(strict_types=1);

namespace App\Infrastructure\MyCards\Controller;

use App\Application\Activation\Command\SendMyCardsVerificationEmailCommand;
use App\Application\GiftCard\Port\GiftCardReadModelRepositoryInterface;
use App\Application\GiftCard\Query\GetGiftCardHistoryQuery;
use App\Domain\GiftCard\ValueObject\CustomerEmail;
use App\Domain\GiftCard\ValueObject\VerificationCode;
use App\Domain\Tenant\Port\TenantQueryRepositoryInterface;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Infrastructure\Activation\Entity\CardAssignment;
use App\Infrastructure\Activation\Repository\CardAssignmentRepository;
use App\Infrastructure\MyCards\Entity\MyCardsRequest;
use App\Infrastructure\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final class MyCardsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly GiftCardReadModelRepositoryInterface $giftCardRepository,
        private readonly CardAssignmentRepository $cardAssignmentRepository,
        private readonly TenantContext $tenantContext,
        private readonly TenantQueryRepositoryInterface $tenantQueryRepository,
    ) {}

    #[Route('/my-cards', name: 'my_cards_form', methods: ['GET'])]
    public function form(): Response
    {
        return $this->render('my-cards/form.html.twig');
    }

    #[Route('/my-cards', name: 'my_cards_submit', methods: ['POST'])]
    public function submit(Request $request): Response
    {
        $email = trim($request->request->getString('email'));

        try {
            CustomerEmail::fromString($email);
        } catch (\InvalidArgumentException) {
            $this->addFlash('error', 'my_cards.error_invalid_email');
            return $this->render('my-cards/form.html.twig', [
                'submitted_email' => $email,
            ]);
        }

        $verificationCode = VerificationCode::generate();

        $myCardsRequest = new MyCardsRequest(
            customerEmail: $email,
            verificationCode: $verificationCode->toString(),
        );

        $this->entityManager->persist($myCardsRequest);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new SendMyCardsVerificationEmailCommand(
            email: $email,
            verificationCode: $verificationCode->toString(),
        ));

        return $this->redirectToRoute('my_cards_verify', ['id' => $myCardsRequest->getId()]);
    }

    #[Route('/my-cards/{id}/verify', name: 'my_cards_verify', methods: ['GET'])]
    public function verifyForm(string $id): Response
    {
        $myCardsRequest = $this->findRequest($id);
        if ($myCardsRequest === null) {
            return $this->renderError('my_cards.error_request_not_found');
        }

        if ($myCardsRequest->isExpired()) {
            $myCardsRequest->markExpired();
            $this->entityManager->flush();
            return $this->renderError('my_cards.error_code_expired');
        }

        return $this->render('my-cards/verify.html.twig', [
            'request_id' => $myCardsRequest->getId(),
            'email' => $myCardsRequest->getCustomerEmail(),
        ]);
    }

    #[Route('/my-cards/{id}/verify', name: 'my_cards_verify_submit', methods: ['POST'])]
    public function verify(Request $request, string $id): Response
    {
        $myCardsRequest = $this->findRequest($id);
        if ($myCardsRequest === null) {
            return $this->renderError('my_cards.error_request_not_found');
        }

        if ($myCardsRequest->isExpired()) {
            $myCardsRequest->markExpired();
            $this->entityManager->flush();
            return $this->renderError('my_cards.error_code_expired');
        }

        $code = trim($request->request->getString('code'));

        if ($code !== $myCardsRequest->getVerificationCode()) {
            $this->addFlash('error', 'my_cards.error_invalid_code');
            return $this->redirectToRoute('my_cards_verify', ['id' => $id]);
        }

        $myCardsRequest->verify();
        $this->entityManager->flush();

        // Bind verified access to the current browser session
        $request->getSession()->set($this->sessionKey($id), true);

        return $this->redirectToRoute('my_cards_list', ['id' => $id]);
    }

    #[Route('/my-cards/{id}/cards', name: 'my_cards_list', methods: ['GET'])]
    public function cards(Request $request, string $id): Response
    {
        $myCardsRequest = $this->findRequest($id);
        if ($myCardsRequest === null) {
            return $this->renderError('my_cards.error_request_not_found');
        }

        if (!$myCardsRequest->isVerified() || !$request->getSession()->get($this->sessionKey($id), false)) {
            return $this->renderError('my_cards.error_not_verified');
        }

        $assignments = $this->cardAssignmentRepository->findByCustomerEmail(
            $myCardsRequest->getCustomerEmail()
        );

        $cards = [];
        $tenantNames = [];

        foreach ($assignments as $assignment) {
            $readModel = $this->giftCardRepository->findById($assignment->getGiftCardId());
            if ($readModel === null) {
                continue;
            }

            $cards[] = $readModel;

            if (!isset($tenantNames[$readModel->tenantId])) {
                try {
                    $tenant = $this->tenantQueryRepository->findById(TenantId::fromString($readModel->tenantId));
                    $tenantNames[$readModel->tenantId] = (string) $tenant->getName();
                } catch (\Throwable) {
                    $tenantNames[$readModel->tenantId] = null;
                }
            }
        }

        return $this->render('my-cards/cards.html.twig', [
            'request_id' => $myCardsRequest->getId(),
            'email' => $myCardsRequest->getCustomerEmail(),
            'cards' => $cards,
            'tenant_names' => $tenantNames,
        ]);
    }

    #[Route('/my-cards/{id}/cards/{cardId}', name: 'my_cards_detail', methods: ['GET'])]
    public function detail(Request $request, string $id, string $cardId): Response
    {
        $myCardsRequest = $this->findRequest($id);
        if ($myCardsRequest === null) {
            return $this->renderError('my_cards.error_request_not_found');
        }

        if (!$myCardsRequest->isVerified() || !$request->getSession()->get($this->sessionKey($id), false)) {
            return $this->renderError('my_cards.error_not_verified');
        }

        // Verify card belongs to this customer
        $assignment = $this->entityManager
            ->getRepository(CardAssignment::class)
            ->findOneBy([
                'giftCardId' => $cardId,
                'customerEmail' => $myCardsRequest->getCustomerEmail(),
            ]);

        if ($assignment === null) {
            return $this->renderError('my_cards.error_access_denied');
        }

        $card = $this->giftCardRepository->findById($cardId);
        if ($card === null) {
            return $this->renderError('my_cards.error_card_not_found');
        }

        $tenantName = null;
        try {
            $tenant = $this->tenantQueryRepository->findById(TenantId::fromString($card->tenantId));
            $tenantName = (string) $tenant->getName();
        } catch (\Throwable) {
        }

        // Load event history — requires tenant context (same pattern as admin)
        $this->tenantContext->setTenantId(TenantId::fromString($card->tenantId));
        $envelope = $this->messageBus->dispatch(new GetGiftCardHistoryQuery($cardId));
        $history = $envelope->last(HandledStamp::class)?->getResult();

        return $this->render('my-cards/detail.html.twig', [
            'request_id' => $myCardsRequest->getId(),
            'card' => $card,
            'tenant_name' => $tenantName,
            'history' => $history,
        ]);
    }

    private function findRequest(string $id): ?MyCardsRequest
    {
        return $this->entityManager->getRepository(MyCardsRequest::class)->find($id);
    }

    private function renderError(string $messageKey): Response
    {
        return $this->render('my-cards/error.html.twig', [
            'error_message' => $messageKey,
        ]);
    }

    private function sessionKey(string $requestId): string
    {
        return 'my_cards_verified_' . $requestId;
    }
}
