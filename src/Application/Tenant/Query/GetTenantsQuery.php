<?php

declare(strict_types=1);

namespace App\Application\Tenant\Query;

final readonly class GetTenantsQuery
{
    public function __construct(
        public int $page = 1,
        public int $limit = 20
    ) {}
}
