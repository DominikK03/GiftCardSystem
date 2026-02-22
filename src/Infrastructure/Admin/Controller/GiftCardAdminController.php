<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Controller;

use App\Application\GiftCard\Command\ActivateCommand;
use App\Application\GiftCard\Command\CancelCommand;
use App\Application\GiftCard\Command\CreateCommand;
use App\Application\GiftCard\Command\ReactivateCommand;
use App\Application\GiftCard\Command\SuspendCommand;
use App\Application\GiftCard\Port\GiftCardReadModelRepositoryInterface;
use App\Application\GiftCard\Query\GetGiftCardHistoryQuery;
use App\Application\Tenant\Command\GenerateInvoiceCommand;
use App\Domain\Tenant\Enum\TenantStatus;
use App\Domain\Tenant\Port\TenantQueryRepositoryInterface;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Infrastructure\Tenant\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GiftCardAdminController extends AbstractController
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly GiftCardReadModelRepositoryInterface $giftCardRepository,
        private readonly TenantQueryRepositoryInterface $tenantQueryRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly TenantContext $tenantContext
    ) {}

    #[Route('/admin/giftcards', name: 'admin_giftcard_index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $statusFilter = $request->query->getString('status') ?: null;
        $tenantFilter = $request->query->getString('tenant') ?: null;
        $idFilter = $request->query->getString('id') ?: null;

        $giftCards = $this->giftCardRepository->findAllPaginated($page, self::PER_PAGE, $statusFilter, $tenantFilter, $idFilter);
        $total = $this->giftCardRepository->countAll($statusFilter, $tenantFilter, $idFilter);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        $tenants = $this->tenantQueryRepository->findAll();

        return $this->render('admin/giftcard/index.html.twig', [
            'giftCards' => $giftCards,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'currentStatus' => $statusFilter ?? '',
            'currentTenant' => $tenantFilter ?? '',
            'currentId' => $idFilter ?? '',
            'tenants' => $tenants,
        ]);
    }

    #[Route('/admin/giftcards/{id}', name: 'admin_giftcard_detail', requirements: ['id' => '[0-9a-f\-]{36}'])]
    public function detail(string $id): Response
    {
        $giftCard = $this->giftCardRepository->findById($id);
        if ($giftCard === null) {
            throw $this->createNotFoundException();
        }

        $this->tenantContext->setTenantId(TenantId::fromString($giftCard->tenantId));

        $envelope = $this->messageBus->dispatch(new GetGiftCardHistoryQuery($id));
        $history = $envelope->last(HandledStamp::class)?->getResult();

        $tenant = null;
        try {
            $tenant = $this->tenantQueryRepository->findById(TenantId::fromString($giftCard->tenantId));
        } catch (\Throwable) {
        }

        return $this->render('admin/giftcard/detail.html.twig', [
            'gc' => $giftCard,
            'history' => $history,
            'tenant' => $tenant,
        ]);
    }

    #[Route('/admin/giftcards/{id}/activate', name: 'admin_giftcard_activate', methods: ['POST'])]
    #[IsGranted('ROLE_OWNER')]
    public function activate(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('gc_activate_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $giftCard = $this->giftCardRepository->findById($id);
        if ($giftCard === null) {
            throw $this->createNotFoundException();
        }

        $this->tenantContext->setTenantId(TenantId::fromString($giftCard->tenantId));

        try {
            $this->messageBus->dispatch(new ActivateCommand(
                id: $id,
                activatedAt: (new \DateTimeImmutable())->format('c')
            ));
            $this->addFlash('success', 'giftcard.activated_success');
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_giftcard_detail', ['id' => $id]);
    }

    #[Route('/admin/giftcards/{id}/suspend', name: 'admin_giftcard_suspend', methods: ['POST'])]
    #[IsGranted('ROLE_OWNER')]
    public function suspend(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('gc_suspend_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $giftCard = $this->giftCardRepository->findById($id);
        if ($giftCard === null) {
            throw $this->createNotFoundException();
        }

        $this->tenantContext->setTenantId(TenantId::fromString($giftCard->tenantId));

        $reason = $request->request->getString('reason', 'Zawieszenie przez administratora');

        try {
            $this->messageBus->dispatch(new SuspendCommand(
                id: $id,
                reason: $reason,
                suspendedAt: (new \DateTimeImmutable())->format('c'),
                suspensionDurationSeconds: 0
            ));
            $this->addFlash('success', 'giftcard.suspended_success');
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_giftcard_detail', ['id' => $id]);
    }

    #[Route('/admin/giftcards/{id}/reactivate', name: 'admin_giftcard_reactivate', methods: ['POST'])]
    #[IsGranted('ROLE_OWNER')]
    public function reactivate(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('gc_reactivate_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $giftCard = $this->giftCardRepository->findById($id);
        if ($giftCard === null) {
            throw $this->createNotFoundException();
        }

        $this->tenantContext->setTenantId(TenantId::fromString($giftCard->tenantId));

        try {
            $this->messageBus->dispatch(new ReactivateCommand(
                id: $id,
                reason: 'Reaktywacja przez administratora',
                reactivatedAt: (new \DateTimeImmutable())->format('c')
            ));
            $this->addFlash('success', 'giftcard.reactivated_success');
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_giftcard_detail', ['id' => $id]);
    }

    #[Route('/admin/giftcards/{id}/cancel', name: 'admin_giftcard_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_OWNER')]
    public function cancel(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('gc_cancel_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $giftCard = $this->giftCardRepository->findById($id);
        if ($giftCard === null) {
            throw $this->createNotFoundException();
        }

        $this->tenantContext->setTenantId(TenantId::fromString($giftCard->tenantId));

        $reason = $request->request->getString('reason', 'Anulowanie przez administratora');

        try {
            $this->messageBus->dispatch(new CancelCommand(
                id: $id,
                reason: $reason,
                cancelledAt: (new \DateTimeImmutable())->format('c')
            ));
            $this->addFlash('success', 'giftcard.cancelled_success');
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_giftcard_detail', ['id' => $id]);
    }

    #[Route('/admin/giftcards/issue', name: 'admin_giftcard_issue', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_OWNER')]
    public function issue(Request $request): Response
    {
        $tenants = array_filter(
            $this->tenantQueryRepository->findAll(),
            fn ($t) => $t->getStatus() === TenantStatus::ACTIVE
        );

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('giftcard_issue', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $tenantId = trim($request->request->getString('tenantId'));
            $currency = trim($request->request->getString('currency'));
            $vatRate = $request->request->getInt('vatRate');
            $quantities = $request->request->all('quantity');
            $amounts = $request->request->all('amount');

            $errors = [];

            if (empty($tenantId)) {
                $errors[] = 'Wybierz tenanta.';
            }

            $lines = [];
            foreach ($quantities as $i => $qty) {
                $q = (int) $qty;
                $a = (int) ($amounts[$i] ?? 0);

                if ($q < 1 || $a < 1) {
                    continue;
                }

                $lines[] = ['quantity' => $q, 'amount' => $a];
            }

            if (empty($lines)) {
                $errors[] = 'Dodaj co najmniej jedną pozycję z ilością i wartością większą od 0.';
            }

            if (!empty($errors)) {
                return $this->render('admin/giftcard/issue.html.twig', [
                    'tenants' => $tenants,
                    'errors' => $errors,
                    'formData' => [
                        'tenantId' => $tenantId,
                        'currency' => $currency,
                        'vatRate' => $vatRate,
                        'quantities' => $quantities,
                        'amounts' => $amounts,
                    ],
                ]);
            }

            $createdCount = 0;
            foreach ($lines as $line) {
                for ($i = 0; $i < $line['quantity']; $i++) {
                    $this->messageBus->dispatch(new CreateCommand(
                        amount: $line['amount'],
                        currency: $currency,
                        tenantId: $tenantId
                    ));
                    $createdCount++;
                }
            }

            $invoiceItems = [];
            foreach ($lines as $line) {
                $invoiceItems[] = [
                    'description' => 'Karta podarunkowa',
                    'quantity' => $line['quantity'],
                    'unitPriceAmount' => $line['amount'],
                ];
            }

            $this->messageBus->dispatch(new GenerateInvoiceCommand(
                tenantId: $tenantId,
                items: $invoiceItems,
                currency: $currency,
                vatRate: $vatRate
            ));

            $this->addFlash('success', "Utworzono {$createdCount} kart podarunkowych i wygenerowano fakturę.");

            return $this->redirectToRoute('admin_giftcard_index');
        }

        return $this->render('admin/giftcard/issue.html.twig', [
            'tenants' => $tenants,
            'formData' => [],
        ]);
    }
}
