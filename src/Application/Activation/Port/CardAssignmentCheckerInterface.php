<?php

declare(strict_types=1);

namespace App\Application\Activation\Port;

interface CardAssignmentCheckerInterface
{
    public function isCardAssigned(string $giftCardId): bool;
}
