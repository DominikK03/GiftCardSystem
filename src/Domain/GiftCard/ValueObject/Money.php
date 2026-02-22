<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\ValueObject;

use App\Domain\GiftCard\Exception\InvalidMoneyException;
use Symfony\Component\Intl\Currencies;

final class Money
{
    private readonly int $amount;
    private readonly string $currency;

    public function __construct(int $amount, string $currency)
    {
        if ($amount < 0){
            throw InvalidMoneyException::negativeAmount();
        }
        if (!Currencies::exists($currency)){
            throw InvalidMoneyException::invalidCurrency($currency);
        }
        $this->amount = $amount;
        $this->currency = $currency;
    }

    public static function fromPrimitives(int $amount, string $currency): self
    {
        return new self($amount, $currency);
    }

    /**
     * @return int
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): Money
    {
        if ($this->currency !== $other->currency){
            throw InvalidMoneyException::currencyMismatch($this->currency, $other->currency);
        }
        $newAmount = $this->amount + $other->amount;
        return new Money($newAmount, $this->currency);
    }
    public function subtract(Money $other): Money
    {
        if ($this->currency !== $other->currency){
            throw InvalidMoneyException::currencyMismatch($this->currency, $other->currency);
        }
        $newAmount = $this->amount - $other->amount;
        if ($newAmount < 0){
            throw InvalidMoneyException::negativeAmount();
        }
        return new Money($newAmount, $this->currency);
    }
    public function isGreaterThan(Money $other): bool
    {
        if ($this->currency !== $other->currency){
            throw InvalidMoneyException::currencyMismatch($this->currency, $other->currency);
        }
        return $this->amount > $other->amount;
    }

    public function equals(Money $other): bool
    {
        if ($this->currency !== $other->currency){
            throw InvalidMoneyException::currencyMismatch($this->currency, $other->currency);
        }
        return $this->amount === $other->amount;
    }

    public function isGreaterThanOrEqual(Money $other): bool
    {
        return $this->isGreaterThan($other) || $this->equals($other);
    }

}
