<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class TenantStamp implements StampInterface
{
    public function __construct(
        private readonly string $tenantId
    ) {
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }
}
