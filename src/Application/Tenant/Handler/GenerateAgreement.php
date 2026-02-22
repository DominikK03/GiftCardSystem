<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handler;

use App\Application\Tenant\Command\GenerateAgreementCommand;
use App\Application\Tenant\Port\DocumentPersisterInterface;
use App\Application\Tenant\Port\DocumentStorageInterface;
use App\Application\Tenant\Port\PdfGeneratorInterface;
use App\Application\Tenant\Port\TenantProviderInterface;
use App\Domain\Tenant\Entity\TenantDocument;
use App\Domain\Tenant\ValueObject\DocumentId;
use App\Domain\Tenant\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateAgreement
{
    public function __construct(
        private readonly TenantProviderInterface $tenantProvider,
        private readonly PdfGeneratorInterface $pdfGenerator,
        private readonly DocumentStorageInterface $documentStorage,
        private readonly DocumentPersisterInterface $documentPersister
    ) {
    }

    public function __invoke(GenerateAgreementCommand $command): string
    {
        $tenant = $this->tenantProvider->loadById(TenantId::fromString($command->tenantId));

        $pdfContent = $this->pdfGenerator->generateCooperationAgreement($tenant);

        $documentId = DocumentId::generate();
        $filename = $documentId->toString() . '.pdf';
        $originalName = sprintf(
            'Umowa_%s_%s.pdf',
            str_replace(' ', '_', $tenant->getName()->toString()),
            date('Y-m-d')
        );
        $directory = sprintf('tenants/%s/agreements', $tenant->getId()->toString());

        $storagePath = $this->documentStorage->store($pdfContent, $directory, $filename);

        $document = TenantDocument::createAgreement(
            $documentId->toString(),
            $tenant->getId()->toString(),
            $filename,
            $originalName,
            strlen($pdfContent),
            $storagePath
        );

        $this->documentPersister->handle($document);

        return $documentId->toString();
    }
}
