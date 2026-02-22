<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Command;

final readonly class DecreaseBalanceCommand
{
    public function __construct(
        public string $id,
        public int    $amount,
        public string $currency,
        public string $reason,
        public string $decreasedAt
    ) {}
}
