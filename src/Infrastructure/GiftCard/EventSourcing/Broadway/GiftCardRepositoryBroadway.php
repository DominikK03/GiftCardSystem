<?php

namespace App\Infrastructure\GiftCard\EventSourcing\Broadway;

use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\Port\GiftCardRepository;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use Broadway\Repository\AggregateNotFoundException;
use Broadway\Repository\Repository as BroadwayRepository;

final class GiftCardRepositoryBroadway implements GiftCardRepository
{
    public function __construct(
        /** @var BroadwayRepository<GiftCard> */
        private readonly BroadwayRepository $inner
    )
    {
    }

    public function load(GiftCardId $id): ?GiftCard
    {
        try {
            /** @var GiftCard $giftCard */
            $giftCard = $this->inner->load($id->toString());
            return $giftCard;
        } catch (AggregateNotFoundException) {
            return null;
        }
    }

    public function save(GiftCard $giftCard): void
    {
        $this->inner->save($giftCard);
    }
}
