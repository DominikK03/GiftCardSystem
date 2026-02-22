<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Event;

final class GiftCardSuspended
{
    public function __construct(
        public readonly string $id,
        public readonly string $reason,
        public readonly string $suspendedAt,
        public readonly int $suspensionDurationSeconds
    ) {}
}
