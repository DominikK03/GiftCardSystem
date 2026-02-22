<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Port;

use App\Domain\GiftCard\Aggregate\GiftCard;

interface GiftCardPersisterInterface
{
    public function handle(GiftCard $giftCard): void;
}
