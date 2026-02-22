<?php

namespace App\Application\GiftCard\Command;

final readonly class AdjustBalanceCommand
{
    public function __construct(
        public string $id,
        public int    $amount,
        public string $currency,
        public string $reason,
        public string $adjustedAt
    )
    {
    }

}
