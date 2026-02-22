<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Event;

final class GiftCardReactivated
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $reason,
        public readonly string $reactivatedAt,
        public readonly ?string $newExpiresAt
    ) {}
}
