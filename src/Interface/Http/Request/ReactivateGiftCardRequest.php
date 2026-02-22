<?php

declare(strict_types=1);

namespace App\Interface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class ReactivateGiftCardRequest
{
    #[Assert\Type(type: ['string', 'null'])]
    public mixed $reason = null;

    #[Assert\Type(type: ['string', 'null'])]
    #[Assert\Regex(pattern: IsoDateTimeFormat::PATTERN, message: 'Invalid date format, expected ISO-8601')]
    public mixed $reactivatedAt = null;

    public static function fromArray(array $data): self
    {
        $self = new self();
        $self->reason = $data['reason'] ?? null;
        $self->reactivatedAt = $data['reactivatedAt'] ?? null;

        return $self;
    }
}
