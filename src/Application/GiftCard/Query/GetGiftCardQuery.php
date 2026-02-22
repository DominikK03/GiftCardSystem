<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Query;

final readonly class GetGiftCardQuery
{
    public function __construct(
        public string $id
    ) {}
}
