<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Event;

final class GiftCardBalanceAdjusted
{
    public function __construct(
        public readonly string $id,
        public readonly int $adjustmentAmount,
        public readonly string $adjustmentCurrency,
        public readonly string $reason,
        public readonly string $adjustedAt
    ) {}
}
