<?php

declare(strict_types=1);

namespace App\Application\Tenant\Port;

use App\Domain\Tenant\Entity\TenantDocument;

interface DocumentPersisterInterface
{
    public function handle(TenantDocument $document): void;
}
