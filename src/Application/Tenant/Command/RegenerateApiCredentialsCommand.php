<?php

declare(strict_types=1);

namespace App\Application\Tenant\Command;

final readonly class RegenerateApiCredentialsCommand
{
    public function __construct(
        public string $tenantId
    ) {
    }
}
