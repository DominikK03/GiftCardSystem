<?php

namespace App\Application\GiftCard\Command;

final readonly class CreateCommand
{
    public function __construct(
        public int     $amount,
        public string  $currency,
        public ?string $expiresAt = null,
        public ?string $tenantId = null
    ) {}
}
