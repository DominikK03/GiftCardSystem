<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Query;

final readonly class GetGiftCardsQuery
{
    public function __construct(
        public string $tenantId,
        public int $page = 1,
        public int $limit = 20,
        public ?string $status = null,
    ) {}
}
