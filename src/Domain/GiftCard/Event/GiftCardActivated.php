<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Event;

final class GiftCardActivated
{
    public function __construct(
        public readonly string $id,
        public readonly string $activatedAt
    ) {}
}
