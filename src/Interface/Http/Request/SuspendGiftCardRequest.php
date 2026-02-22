<?php

declare(strict_types=1);

namespace App\Interface\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class SuspendGiftCardRequest
{
    #[Assert\NotBlank(message: 'reason is required')]
    #[Assert\Type(type: 'string')]
    public mixed $reason = null;

    #[Assert\NotNull(message: 'suspensionDurationSeconds is required')]
    #[Assert\Type(type: 'integer')]
    #[Assert\Positive]
    public mixed $suspensionDurationSeconds = null;

    #[Assert\Type(type: ['string', 'null'])]
    #[Assert\Regex(pattern: IsoDateTimeFormat::PATTERN, message: 'Invalid date format, expected ISO-8601')]
    public mixed $suspendedAt = null;

    public static function fromArray(array $data): self
    {
        $self = new self();
        $self->reason = $data['reason'] ?? null;
        $self->suspensionDurationSeconds = $data['suspensionDurationSeconds'] ?? null;
        $self->suspendedAt = $data['suspendedAt'] ?? null;

        return $self;
    }
}
