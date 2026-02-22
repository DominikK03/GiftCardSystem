<?php

declare(strict_types=1);

namespace App\Interface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class ActivateGiftCardRequest
{
    #[Assert\Type(type: ['string', 'null'])]
    #[Assert\Regex(pattern: IsoDateTimeFormat::PATTERN, message: 'Invalid date format, expected ISO-8601')]
    public mixed $activatedAt = null;

    public static function fromArray(array $data): self
    {
        $self = new self();
        $self->activatedAt = $data['activatedAt'] ?? null;

        return $self;
    }
}
