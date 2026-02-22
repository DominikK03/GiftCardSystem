<?php

declare(strict_types=1);

namespace App\Interface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class RedeemGiftCardRequest
{
    #[Assert\NotNull(message: 'amount is required')]
    #[Assert\Type(type: 'integer')]
    #[Assert\Positive]
    public mixed $amount = null;

    #[Assert\NotBlank(message: 'currency is required')]
    #[Assert\Type(type: 'string')]
    #[Assert\Currency]
    public mixed $currency = null;

    public static function fromArray(array $data): self
    {
        $self = new self();
        $self->amount = $data['amount'] ?? null;
        $self->currency = $data['currency'] ?? null;

        return $self;
    }
}
