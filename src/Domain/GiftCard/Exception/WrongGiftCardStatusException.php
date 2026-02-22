<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use App\Domain\GiftCard\Enum\GiftCardStatus;
use DomainException;

class WrongGiftCardStatusException extends DomainException
{
    public static function create(GiftCardStatus $expectedStatus, GiftCardStatus $actualStatus)
    {
        return new self(sprintf("This operation is available only on %s Gift Cards. Current status: %s", $expectedStatus->value, $actualStatus->value));
    }
    public static function cannotCancel(GiftCardStatus $status)
    {
        return new self(sprintf("Cannot cancel Gift Card with current status: %s", $status->value));
    }
}
