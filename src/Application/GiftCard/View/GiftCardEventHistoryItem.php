<?php

declare(strict_types=1);

namespace App\Application\GiftCard\View;

final readonly class GiftCardEventHistoryItem
{
    public function __construct(
        public string $eventType,
        public int $eventNumber,
        public string $occurredAt,
        public array $eventPayload,
        public string $status,
        public int $balanceAmount,
        public string $balanceCurrency,
        public ?string $expiresAt,
        public ?string $activatedAt,
        public ?string $suspendedAt,
        public ?string $cancelledAt,
        public ?string $expiredAt,
        public ?string $depletedAt,
        public int $suspensionDuration
    ) {}

    public function toArray(): array
    {
        return [
            'event' => [
                'type' => $this->eventType,
                'number' => $this->eventNumber,
                'occurredAt' => $this->occurredAt,
                'payload' => $this->eventPayload
            ],
            'stateAfterEvent' => [
                'status' => $this->status,
                'balance' => [
                    'amount' => $this->balanceAmount,
                    'currency' => $this->balanceCurrency,
                    'formatted' => sprintf('%.2f %s', $this->balanceAmount / 100, $this->balanceCurrency)
                ],
                'expiresAt' => $this->expiresAt,
                'activatedAt' => $this->activatedAt,
                'suspendedAt' => $this->suspendedAt,
                'cancelledAt' => $this->cancelledAt,
                'expiredAt' => $this->expiredAt,
                'depletedAt' => $this->depletedAt,
                'suspensionDuration' => $this->suspensionDuration
            ]
        ];
    }
}
