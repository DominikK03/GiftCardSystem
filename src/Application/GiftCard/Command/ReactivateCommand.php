<?php

namespace App\Application\GiftCard\Command;

final readonly class ReactivateCommand
{
    public function __construct(
        public string $id,
        public ?string $reason,
        public string $reactivatedAt
    )
    {
    }
}
