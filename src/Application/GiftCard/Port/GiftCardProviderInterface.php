<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Port;

use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\ValueObject\GiftCardId;

interface GiftCardProviderInterface
{
    public function loadFromId(GiftCardId $id): GiftCard;

    public function loadFromIdAsSystem(GiftCardId $id): GiftCard;
}
