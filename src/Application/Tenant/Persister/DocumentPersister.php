<?php

declare(strict_types=1);

namespace App\Application\Tenant\Persister;

use App\Application\Tenant\Port\DocumentPersisterInterface;
use App\Domain\Tenant\Entity\TenantDocument;
use App\Domain\Tenant\Port\TenantDocumentRepositoryInterface;

final class DocumentPersister implements DocumentPersisterInterface
{
    public function __construct(
        private readonly TenantDocumentRepositoryInterface $repository
    ) {
    }

    public function handle(TenantDocument $document): void
    {
        $this->repository->save($document);
    }
}
