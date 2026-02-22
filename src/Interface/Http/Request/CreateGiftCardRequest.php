<?php

declare(strict_types=1);

namespace App\Interface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateGiftCardRequest
{
    #[Assert\NotNull(message: 'amount is required')]
    #[Assert\Type(type: 'integer')]
    #[Assert\Positive]
    public mixed $amount = null;

    #[Assert\NotBlank(message: 'currency is required')]
    #[Assert\Type(type: 'string')]
    #[Assert\Currency]
    public mixed $currency = null;

    #[Assert\Type(type: ['string', 'null'])]
    #[Assert\Regex(pattern: IsoDateTimeFormat::PATTERN, message: 'Invalid date format, expected ISO-8601')]
    public mixed $expiresAt = null;

    public static function fromArray(array $data): self
    {
        $self = new self();
        $self->amount = $data['amount'] ?? null;
        $self->currency = $data['currency'] ?? null;
        $self->expiresAt = $data['expiresAt'] ?? null;

        return $self;
    }
}
