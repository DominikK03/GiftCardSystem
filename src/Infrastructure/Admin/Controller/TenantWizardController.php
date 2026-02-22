<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Controller;

use App\Application\Tenant\Command\CreateTenantCommand;
use App\Application\Tenant\Command\GenerateAgreementCommand;
use App\Application\Tenant\Port\DocumentStorageInterface;
use App\Application\Tenant\Port\PdfGeneratorInterface;
use App\Domain\Tenant\Entity\Tenant;
use App\Domain\Tenant\Entity\TenantDocument;
use App\Domain\Tenant\Exception\DocumentNotFoundException;
use App\Domain\Tenant\Port\TenantDocumentRepositoryInterface;
use App\Domain\Tenant\ValueObject\DocumentId;
use App\Domain\Tenant\ValueObject\Address;
use App\Domain\Tenant\ValueObject\ApiKey;
use App\Domain\Tenant\ValueObject\ApiSecret;
use App\Domain\Tenant\ValueObject\NIP;
use App\Domain\Tenant\ValueObject\PhoneNumber;
use App\Domain\Tenant\ValueObject\RepresentativeName;
use App\Domain\Tenant\ValueObject\TenantEmail;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Domain\Tenant\ValueObject\TenantName;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final class TenantWizardController extends AbstractController
{
    private const SESSION_KEY = 'tenant_wizard_data';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly PdfGeneratorInterface $pdfGenerator,
        private readonly DocumentStorageInterface $documentStorage,
        private readonly TenantDocumentRepositoryInterface $documentRepository
    ) {
    }

    #[Route('/admin/tenants/wizard/step1', name: 'admin_tenant_wizard_step1', methods: ['GET', 'POST'])]
    public function step1(Request $request): Response
    {
        $session = $request->getSession();
        $data = $session->get(self::SESSION_KEY, []);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tenant_wizard', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $errors = [];
            $name = trim($request->request->getString('name'));
            $email = trim($request->request->getString('email'));
            $nip = trim($request->request->getString('nip'));
            $phoneNumber = trim($request->request->getString('phoneNumber'));

            try {
                TenantName::fromString($name);
            } catch (\DomainException $e) {
                $errors[] = $e->getMessage();
            }

            try {
                TenantEmail::fromString($email);
            } catch (\DomainException $e) {
                $errors[] = $e->getMessage();
            }

            try {
                NIP::fromString($nip);
            } catch (\DomainException $e) {
                $errors[] = $e->getMessage();
            }

            try {
                PhoneNumber::fromString($phoneNumber);
            } catch (\DomainException $e) {
                $errors[] = $e->getMessage();
            }

            if (!empty($errors)) {
                return $this->render('admin/wizard/step1.html.twig', [
                    'data' => [
                        'name' => $name,
                        'email' => $email,
                        'nip' => $nip,
                        'phoneNumber' => $phoneNumber,
                    ],
                    'errors' => $errors,
                ]);
            }

            $data['name'] = $name;
            $data['email'] = $email;
            $data['nip'] = $nip;
            $data['phoneNumber'] = $phoneNumber;
            $session->set(self::SESSION_KEY, $data);

            return $this->redirectToRoute('admin_tenant_wizard_step2');
        }

        return $this->render('admin/wizard/step1.html.twig', [
            'data' => $data,
        ]);
    }

    #[Route('/admin/tenants/wizard/step2', name: 'admin_tenant_wizard_step2', methods: ['GET', 'POST'])]
    public function step2(Request $request): Response
    {
        $session = $request->getSession();
        $data = $session->get(self::SESSION_KEY, []);

        if (empty($data['name'])) {
            return $this->redirectToRoute('admin_tenant_wizard_step1');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tenant_wizard', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $errors = [];
            $street = trim($request->request->getString('street'));
            $city = trim($request->request->getString('city'));
            $postalCode = trim($request->request->getString('postalCode'));
            $country = trim($request->request->getString('country'));
            $firstName = trim($request->request->getString('firstName'));
            $lastName = trim($request->request->getString('lastName'));

            try {
                Address::create($street, $city, $postalCode, $country);
            } catch (\DomainException $e) {
                $errors[] = $e->getMessage();
            }

            try {
                RepresentativeName::create($firstName, $lastName);
            } catch (\DomainException $e) {
                $errors[] = $e->getMessage();
            }

            if (!empty($errors)) {
                return $this->render('admin/wizard/step2.html.twig', [
                    'data' => [
                        'street' => $street,
                        'city' => $city,
                        'postalCode' => $postalCode,
                        'country' => $country,
                        'firstName' => $firstName,
                        'lastName' => $lastName,
                    ],
                    'errors' => $errors,
                ]);
            }

            $data['street'] = $street;
            $data['city'] = $city;
            $data['postalCode'] = $postalCode;
            $data['country'] = $country;
            $data['firstName'] = $firstName;
            $data['lastName'] = $lastName;
            $session->set(self::SESSION_KEY, $data);

            return $this->redirectToRoute('admin_tenant_wizard_step3');
        }

        return $this->render('admin/wizard/step2.html.twig', [
            'data' => $data,
        ]);
    }

    #[Route('/admin/tenants/wizard/step3', name: 'admin_tenant_wizard_step3', methods: ['GET'])]
    public function step3(Request $request): Response
    {
        $data = $request->getSession()->get(self::SESSION_KEY, []);

        if (empty($data['street'])) {
            return $this->redirectToRoute('admin_tenant_wizard_step2');
        }

        return $this->render('admin/wizard/step3.html.twig', [
            'data' => $data,
        ]);
    }

    #[Route('/admin/tenants/wizard/preview-pdf', name: 'admin_tenant_wizard_preview_pdf', methods: ['GET'])]
    public function previewPdf(Request $request): Response
    {
        $data = $request->getSession()->get(self::SESSION_KEY, []);

        if (empty($data['street'])) {
            return new Response('Brak danych w sesji', Response::HTTP_BAD_REQUEST);
        }

        $tenant = $this->buildTemporaryTenant($data);
        $pdfContent = $this->pdfGenerator->generateCooperationAgreement($tenant);

        return new Response($pdfContent, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="podglad_umowy.pdf"',
        ]);
    }

    #[Route('/admin/tenants/wizard/step4', name: 'admin_tenant_wizard_step4', methods: ['GET', 'POST'])]
    public function step4(Request $request): Response
    {
        $session = $request->getSession();
        $data = $session->get(self::SESSION_KEY, []);

        if (empty($data['street'])) {
            return $this->redirectToRoute('admin_tenant_wizard_step2');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tenant_wizard_confirm', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $envelope = $this->messageBus->dispatch(new CreateTenantCommand(
                name: $data['name'],
                email: $data['email'],
                nip: $data['nip'],
                street: $data['street'],
                city: $data['city'],
                postalCode: $data['postalCode'],
                country: $data['country'],
                phoneNumber: $data['phoneNumber'],
                representativeFirstName: $data['firstName'],
                representativeLastName: $data['lastName']
            ));

            $handledStamp = $envelope->last(HandledStamp::class);
            $tenantId = $handledStamp->getResult();

            $this->messageBus->dispatch(new GenerateAgreementCommand(
                tenantId: $tenantId
            ));

            $uploadedFile = $request->files->get('signed_agreement');
            if ($uploadedFile !== null) {
                if ($uploadedFile->getMimeType() !== 'application/pdf') {
                    $this->addFlash('error', 'Signed agreement must be a PDF file.');

                    return $this->redirectToRoute('admin_tenant_detail', ['id' => $tenantId]);
                }

                if ($uploadedFile->getSize() > 10 * 1024 * 1024) {
                    $this->addFlash('error', 'Signed agreement must not exceed 10 MB.');

                    return $this->redirectToRoute('admin_tenant_detail', ['id' => $tenantId]);
                }

                $content = file_get_contents($uploadedFile->getPathname());
                $filename = sprintf('signed_agreement_%s.pdf', date('Ymd_His'));
                $directory = sprintf('tenants/%s/agreements', $tenantId);
                $storagePath = $this->documentStorage->store($content, $directory, $filename);

                $document = TenantDocument::createSignedAgreement(
                    DocumentId::generate()->toString(),
                    $tenantId,
                    $filename,
                    $uploadedFile->getClientOriginalName(),
                    $uploadedFile->getSize(),
                    $storagePath
                );
                $this->documentRepository->save($document);
            }

            $session->remove(self::SESSION_KEY);

            $this->addFlash('success', 'Tenant został utworzony pomyślnie. Umowa współpracy została wygenerowana.');

            return $this->redirectToRoute('admin_tenant_detail', ['id' => $tenantId]);
        }

        return $this->render('admin/wizard/step4.html.twig', [
            'data' => $data,
        ]);
    }

    #[Route('/admin/tenants/document/{documentId}/download', name: 'admin_tenant_document_download', methods: ['GET'])]
    public function downloadDocument(string $documentId): Response
    {
        $document = $this->documentRepository->findById($documentId);

        if ($document === null) {
            throw DocumentNotFoundException::withId($documentId);
        }

        $content = $this->documentStorage->read($document->getStoragePath());

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => $document->getMimeType(),
            'Content-Disposition' => sprintf('attachment; filename="%s"', $document->getOriginalName()),
            'Content-Length' => $document->getSize(),
        ]);
    }

    #[Route('/admin/tenants/document/{documentId}/preview', name: 'admin_tenant_document_preview', methods: ['GET'])]
    public function previewDocument(string $documentId): Response
    {
        $document = $this->documentRepository->findById($documentId);

        if ($document === null) {
            throw DocumentNotFoundException::withId($documentId);
        }

        $content = $this->documentStorage->read($document->getStoragePath());

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => $document->getMimeType(),
            'Content-Disposition' => sprintf('inline; filename="%s"', $document->getOriginalName()),
            'Content-Length' => $document->getSize(),
        ]);
    }

    private function buildTemporaryTenant(array $data): Tenant
    {
        return Tenant::create(
            TenantId::generate(),
            TenantName::fromString($data['name']),
            TenantEmail::fromString($data['email']),
            NIP::fromString($data['nip']),
            Address::create($data['street'], $data['city'], $data['postalCode'], $data['country']),
            PhoneNumber::fromString($data['phoneNumber']),
            RepresentativeName::create($data['firstName'], $data['lastName']),
            ApiKey::generate(),
            ApiSecret::generate()
        );
    }
}
