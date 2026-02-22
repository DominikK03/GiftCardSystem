<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handler;

use App\Application\Tenant\Command\GenerateInvoiceCommand;
use App\Application\Tenant\Port\DocumentPersisterInterface;
use App\Application\Tenant\Port\DocumentStorageInterface;
use App\Application\Tenant\Port\PdfGeneratorInterface;
use App\Application\Tenant\Port\TenantProviderInterface;
use App\Domain\Tenant\Entity\TenantDocument;
use App\Domain\Tenant\Port\TenantDocumentRepositoryInterface;
use App\Domain\Tenant\ValueObject\DocumentId;
use App\Domain\Tenant\ValueObject\InvoiceNumber;
use App\Domain\Tenant\ValueObject\TenantId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateInvoice
{
    public function __construct(
        private readonly TenantProviderInterface $tenantProvider,
        private readonly PdfGeneratorInterface $pdfGenerator,
        private readonly DocumentStorageInterface $documentStorage,
        private readonly DocumentPersisterInterface $documentPersister,
        private readonly TenantDocumentRepositoryInterface $documentRepository
    ) {
    }

    public function __invoke(GenerateInvoiceCommand $command): string
    {
        $tenant = $this->tenantProvider->loadById(TenantId::fromString($command->tenantId));

        $now = new \DateTimeImmutable();
        $year = (int) $now->format('Y');
        $month = (int) $now->format('m');

        $nextNumber = $this->documentRepository->getNextInvoiceNumber($year, $month);
        $invoiceNumber = InvoiceNumber::generate($year, $month, $nextNumber);

        $pdfContent = $this->pdfGenerator->generateInvoice(
            $tenant,
            $invoiceNumber->toString(),
            $command->items,
            $command->currency,
            $command->vatRate
        );

        $documentId = DocumentId::generate();
        $filename = $documentId->toString() . '.pdf';
        $originalName = sprintf(
            'Faktura_%s_%s.pdf',
            str_replace('/', '-', $invoiceNumber->toString()),
            $tenant->getName()->toString()
        );
        $directory = sprintf('tenants/%s/invoices/%04d/%02d', $tenant->getId()->toString(), $year, $month);

        $storagePath = $this->documentStorage->store($pdfContent, $directory, $filename);

        $document = TenantDocument::createInvoice(
            $documentId->toString(),
            $tenant->getId()->toString(),
            $filename,
            $originalName,
            strlen($pdfContent),
            $storagePath,
            [
                'invoiceNumber' => $invoiceNumber->toString(),
                'items' => $command->items,
                'currency' => $command->currency,
                'vatRate' => $command->vatRate,
            ]
        );

        $this->documentPersister->handle($document);

        return $documentId->toString();
    }
}
