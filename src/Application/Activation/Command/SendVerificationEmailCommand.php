<?php

declare(strict_types=1);

namespace App\Application\Activation\Command;

final readonly class SendVerificationEmailCommand
{
    public function __construct(
        public string $email,
        public string $verificationCode,
    ) {}
}
