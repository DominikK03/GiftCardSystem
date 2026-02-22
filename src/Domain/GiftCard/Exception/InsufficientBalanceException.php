<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use App\Domain\GiftCard\ValueObject\Money;

class InsufficientBalanceException extends \DomainException
{
    public static function notEnoughFunds(Money $balance, Money $requested): self
    {
        return new self(
            sprintf(
                'Insufficient balance. Available: %d %s, Requested: %d %s',
                $balance->getAmount(),
                $balance->getCurrency(),
                $requested->getAmount(),
                $requested->getCurrency()
            )
        );
    }

}
