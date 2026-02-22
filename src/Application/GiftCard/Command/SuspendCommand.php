<?php

namespace App\Application\GiftCard\Command;

final readonly class SuspendCommand
{
    public function __construct(
        public string $id,
        public string $reason,
        public string $suspendedAt,
        public int $suspensionDurationSeconds
    )
    {
    }
}
