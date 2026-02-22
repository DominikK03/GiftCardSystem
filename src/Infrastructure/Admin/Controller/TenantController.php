<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Controller;

use App\Domain\Tenant\Enum\DocumentType;
use App\Domain\Tenant\Enum\TenantStatus;
use App\Domain\Tenant\Port\TenantDocumentRepositoryInterface;
use App\Domain\Tenant\Port\TenantQueryRepositoryInterface;
use App\Domain\Tenant\ValueObject\Address;
use App\Domain\Tenant\ValueObject\PhoneNumber;
use App\Domain\Tenant\ValueObject\RepresentativeName;
use App\Domain\Tenant\ValueObject\TenantEmail;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Domain\Tenant\ValueObject\TenantName;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TenantController extends AbstractController
{
    public function __construct(
        private readonly TenantQueryRepositoryInterface $tenantQueryRepository,
        private readonly TenantDocumentRepositoryInterface $documentRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('/admin/tenants', name: 'admin_tenant_index')]
    public function index(Request $request): Response
    {
        $statusFilter = $request->query->getString('status');
        $nameFilter = $request->query->getString('name');
        $nipFilter = $request->query->getString('nip');

        $tenants = $this->tenantQueryRepository->findAll();

        if ($statusFilter !== '') {
            $tenants = array_filter(
                $tenants,
                fn ($t) => $t->getStatus()->value === $statusFilter
            );
        }

        if ($nameFilter !== '') {
            $tenants = array_filter(
                $tenants,
                fn ($t) => str_contains(mb_strtolower((string) $t->getName()), mb_strtolower($nameFilter))
            );
        }

        if ($nipFilter !== '') {
            $tenants = array_filter(
                $tenants,
                fn ($t) => str_contains((string) $t->getNip(), $nipFilter)
            );
        }

        return $this->render('admin/tenant/index.html.twig', [
            'tenants' => $tenants,
            'currentStatus' => $statusFilter,
            'currentName' => $nameFilter,
            'currentNip' => $nipFilter,
            'statuses' => TenantStatus::cases(),
        ]);
    }

    #[Route('/admin/tenants/{id}', name: 'admin_tenant_detail', requirements: ['id' => '[0-9a-f\-]{36}'])]
    public function detail(string $id): Response
    {
        $tenant = $this->tenantQueryRepository->findById(TenantId::fromString($id));
        $documents = $this->documentRepository->findByTenantId($id);

        $agreementTypes = [
            DocumentType::SIGNED_COOPERATION_AGREEMENT,
            DocumentType::COOPERATION_AGREEMENT,
        ];

        $agreement = null;
        foreach ($agreementTypes as $type) {
            foreach ($documents as $doc) {
                if ($doc->getType() === $type) {
                    $agreement = $doc;
                    break 2;
                }
            }
        }

        return $this->render('admin/tenant/detail.html.twig', [
            'tenant' => $tenant,
            'documents' => $documents,
            'agreement' => $agreement,
        ]);
    }

    #[Route('/admin/tenants/{id}/edit', name: 'admin_tenant_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $tenant = $this->tenantQueryRepository->findById(TenantId::fromString($id));

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tenant_edit', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $errors = [];

            $name = trim($request->request->getString('name'));
            $email = trim($request->request->getString('email'));
            $phone = trim($request->request->getString('phone'));
            $street = trim($request->request->getString('street'));
            $city = trim($request->request->getString('city'));
            $postalCode = trim($request->request->getString('postalCode'));
            $country = trim($request->request->getString('country'));
            $firstName = trim($request->request->getString('firstName'));
            $lastName = trim($request->request->getString('lastName'));

            try {
                $tenant->updateName(TenantName::fromString($name));
            } catch (\DomainException $e) {
                $errors[] = $e->getMessage();
            }

            try {
                $tenant->updateEmail(TenantEmail::fromString($email));
            } catch (\DomainException $e) {
                $errors[] = $e->getMessage();
            }

            try {
                $tenant->updatePhoneNumber(PhoneNumber::fromString($phone));
            } catch (\DomainException $e) {
                $errors[] = $e->getMessage();
            }

            try {
                $tenant->updateAddress(Address::create($street, $city, $postalCode, $country));
            } catch (\DomainException $e) {
                $errors[] = $e->getMessage();
            }

            try {
                $tenant->updateRepresentativeName(RepresentativeName::create($firstName, $lastName));
            } catch (\DomainException $e) {
                $errors[] = $e->getMessage();
            }

            if (!empty($errors)) {
                return $this->render('admin/tenant/edit.html.twig', [
                    'tenant' => $tenant,
                    'errors' => $errors,
                ]);
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'tenant.updated_success');

            return $this->redirectToRoute('admin_tenant_detail', ['id' => $id]);
        }

        return $this->render('admin/tenant/edit.html.twig', [
            'tenant' => $tenant,
        ]);
    }

    #[Route('/admin/tenants/{id}/suspend', name: 'admin_tenant_suspend', methods: ['POST'])]
    #[IsGranted('ROLE_OWNER')]
    public function suspend(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('tenant_suspend_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $tenant = $this->tenantQueryRepository->findById(TenantId::fromString($id));

        try {
            $tenant->suspend();
            $this->entityManager->flush();
            $this->addFlash('success', 'tenant.suspended_success');
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_detail', ['id' => $id]);
    }

    #[Route('/admin/tenants/{id}/reactivate', name: 'admin_tenant_reactivate', methods: ['POST'])]
    #[IsGranted('ROLE_OWNER')]
    public function reactivate(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('tenant_reactivate_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $tenant = $this->tenantQueryRepository->findById(TenantId::fromString($id));

        try {
            $tenant->reactivate();
            $this->entityManager->flush();
            $this->addFlash('success', 'tenant.reactivated_success');
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_detail', ['id' => $id]);
    }

    #[Route('/admin/tenants/{id}/cancel', name: 'admin_tenant_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_OWNER')]
    public function cancel(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('tenant_cancel_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $tenant = $this->tenantQueryRepository->findById(TenantId::fromString($id));

        try {
            $tenant->cancel();
            $this->entityManager->flush();
            $this->addFlash('success', 'tenant.cancelled_success');
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_detail', ['id' => $id]);
    }
}
