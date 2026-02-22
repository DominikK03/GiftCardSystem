<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Command;

final readonly class ExpireCommand
{
    public function __construct(
        public string $id,
        public string $expiredAt
    ) {}
}
