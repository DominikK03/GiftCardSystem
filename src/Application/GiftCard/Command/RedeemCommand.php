<?php

namespace App\Application\GiftCard\Command;
final readonly class RedeemCommand
{
    public function __construct(
        public string $giftCardId,
        public int    $amount,
        public string $currency
    ) {}
}
