<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use App\Domain\GiftCard\Enum\GiftCardStatus;
use DomainException;

class GiftCardAlreadyActivatedException extends DomainException
{
    public static function create(GiftCardStatus $status) : self
    {
        return new self(sprintf('Gift card is already active. Current status: %s', $status->value));
    }

}
