<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Port;

use App\Application\GiftCard\ReadModel\GiftCardReadModel;

interface GiftCardReadModelWriterInterface
{
    public function save(GiftCardReadModel $giftCard): void;
}
