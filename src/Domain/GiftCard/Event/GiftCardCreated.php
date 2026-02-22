<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Event;

final class GiftCardCreated
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $createdAt,
        public readonly string $expiresAt,
        public readonly ?string $cardNumber = null,
        public readonly ?string $pin = null
    ) {}
}
