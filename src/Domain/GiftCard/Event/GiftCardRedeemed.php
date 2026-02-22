<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Event;

final class GiftCardRedeemed
{
    public function __construct(
        public readonly string $id,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $redeemedAt
    ) {}
}
