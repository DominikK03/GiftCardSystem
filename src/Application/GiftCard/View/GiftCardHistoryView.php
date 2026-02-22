<?php

declare(strict_types=1);

namespace App\Application\GiftCard\View;

final readonly class GiftCardHistoryView
{
    /**
     * @param GiftCardEventHistoryItem[] $history
     */
    public function __construct(
        public string $giftCardId,
        public array $history
    ) {}

    public function toArray(): array
    {
        return [
            'giftCardId' => $this->giftCardId,
            'totalEvents' => count($this->history),
            'history' => array_map(
                fn(GiftCardEventHistoryItem $item) => $item->toArray(),
                $this->history
            )
        ];
    }
}
