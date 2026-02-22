<?php

namespace App\Application\GiftCard\Command;

final readonly class CancelCommand
{
    public function __construct(
        public string $id,
        public ?string $reason,
        public string $cancelledAt
    )
    {
    }
}
