<?php

declare(strict_types=1);

namespace App\Application\Tenant\Query;

final readonly class GetTenantQuery
{
    public function __construct(
        public string $id
    ) {}
}
