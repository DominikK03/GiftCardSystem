<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use DomainException;

final class InvalidSuspensionStateException extends DomainException
{
    public static function suspensionDurationNotSet(): self
    {
        return new self('Suspension duration is not set for this gift card');
    }
}
