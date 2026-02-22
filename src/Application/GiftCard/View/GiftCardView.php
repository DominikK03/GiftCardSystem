<?php

declare(strict_types=1);

namespace App\Application\GiftCard\View;

final readonly class GiftCardView
{
    public function __construct(
        public string $id,
        public int $balanceAmount,
        public string $balanceCurrency,
        public int $initialAmount,
        public string $initialCurrency,
        public string $status,
        public ?string $expiresAt,
        public string $createdAt,
        public ?string $activatedAt,
        public ?string $suspendedAt,
        public ?string $cancelledAt,
        public ?string $expiredAt,
        public ?string $depletedAt,
        public int $suspensionDuration,
        public string $updatedAt
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'balance' => [
                'amount' => $this->balanceAmount,
                'currency' => $this->balanceCurrency,
                'formatted' => sprintf('%.2f %s', $this->balanceAmount / 100, $this->balanceCurrency)
            ],
            'initialAmount' => [
                'amount' => $this->initialAmount,
                'currency' => $this->initialCurrency,
                'formatted' => sprintf('%.2f %s', $this->initialAmount / 100, $this->initialCurrency)
            ],
            'status' => $this->status,
            'expiresAt' => $this->expiresAt,
            'createdAt' => $this->createdAt,
            'activatedAt' => $this->activatedAt,
            'suspendedAt' => $this->suspendedAt,
            'cancelledAt' => $this->cancelledAt,
            'expiredAt' => $this->expiredAt,
            'depletedAt' => $this->depletedAt,
            'suspensionDuration' => $this->suspensionDuration,
            'updatedAt' => $this->updatedAt
        ];
    }
}
