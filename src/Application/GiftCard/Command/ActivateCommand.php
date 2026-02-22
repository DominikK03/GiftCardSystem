<?php

namespace App\Application\GiftCard\Command;

final readonly class ActivateCommand
{
    public function __construct(
        public string $id,
        public string $activatedAt
    )
    {
    }
}
