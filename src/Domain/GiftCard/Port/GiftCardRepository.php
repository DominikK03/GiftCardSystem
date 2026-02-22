<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Port;

use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\ValueObject\GiftCardId;

interface GiftCardRepository
{
    public function load(GiftCardId $id): ?GiftCard;
    public function save(GiftCard $giftCard): void;
}
