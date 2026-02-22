<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Exception;

use InvalidArgumentException;

class InvalidMoneyException extends InvalidArgumentException
{
    public static function negativeAmount(): self
    {
        return new self('Money cannot have negative amount', 0);
    }
    public static function invalidCurrency(string $currency): self
    {
        return new self(sprintf('Currency %s does not exist', $currency),0);
    }
    public static function currencyMismatch(string $currency1, string $currency2): self
    {
        return new self(sprintf('Cannot operate on different currencies: %s and %s', $currency1, $currency2), 0);
    }
}
