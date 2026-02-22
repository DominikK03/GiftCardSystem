<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Event;

final readonly class GiftCardBalanceDecreased
{
    public function __construct(
        public string $id,
        public int $amount,
        public string $currency,
        public string $reason,
        public string $decreasedAt
    ) {}
}
