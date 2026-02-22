<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use App\Domain\GiftCard\ValueObject\GiftCardId;

final class GiftCardNotFoundException extends \RuntimeException
{
    public static function forId(GiftCardId $id)
    {
        return new self(sprintf('Gift card %s not found', $id->toString()));
    }
}
