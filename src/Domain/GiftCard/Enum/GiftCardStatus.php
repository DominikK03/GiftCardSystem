<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Enum;

enum GiftCardStatus: string
{
    case INACTIVE = 'inactive';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case DEPLETED = 'depleted';
    case CANCELLED = 'cancelled';
    case SUSPENDED = 'suspended';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function equals(GiftCardStatus $other): bool
    {
        return $this === $other;
    }
}
