<?php

declare(strict_types=1);

namespace App\Application\User\Query;

final readonly class GetUsersQuery
{
    public function __construct(
        public int $page = 1,
        public int $limit = 20
    ) {}
}
