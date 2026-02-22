<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Persister;

use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\Port\GiftCardRepository;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;

final class GiftCardPersister implements GiftCardPersisterInterface
{
    public function __construct(
        private readonly GiftCardRepository $repository
    ) {}

    public function handle(GiftCard $giftCard): void
    {
        $this->repository->save($giftCard);
    }


}
