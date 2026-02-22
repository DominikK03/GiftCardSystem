<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Port;

use App\Domain\Tenant\Entity\TenantDocument;
use App\Domain\Tenant\Enum\DocumentType;

interface TenantDocumentRepositoryInterface
{
    public function save(TenantDocument $document): void;

    public function findById(string $id): ?TenantDocument;

    /** @return TenantDocument[] */
    public function findByTenantId(string $tenantId): array;

    /** @return TenantDocument[] */
    public function findByTenantIdAndType(string $tenantId, DocumentType $type): array;

    public function getNextInvoiceNumber(int $year, int $month): int;

    public function countInvoicesSince(\DateTimeImmutable $since): int;
}
