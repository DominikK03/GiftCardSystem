<?php

declare(strict_types=1);

namespace App\Application\GiftCard\ReadModel;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gift_cards_read')]
class GiftCardReadModel
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(type: 'string', length: 36)]
    public string $tenantId;

    #[ORM\Column(type: 'integer')]
    public int $balanceAmount;

    #[ORM\Column(type: 'string', length: 3)]
    public string $balanceCurrency;

    #[ORM\Column(type: 'integer')]
    public int $initialAmount;

    #[ORM\Column(type: 'string', length: 3)]
    public string $initialCurrency;

    #[ORM\Column(type: 'string', length: 20)]
    public string $status;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $activatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $suspendedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $expiredAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $depletedAt = null;

    #[ORM\Column(type: 'integer')]
    public int $suspensionDuration = 0;

    #[ORM\Column(type: 'string', length: 12, nullable: true, unique: true)]
    public ?string $cardNumber = null;

    #[ORM\Column(type: 'string', length: 8, nullable: true)]
    public ?string $pin = null;

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $tenantId,
        int $balanceAmount,
        string $balanceCurrency,
        int $initialAmount,
        string $initialCurrency,
        string $status,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $expiresAt = null,
        ?string $cardNumber = null,
        ?string $pin = null
    ) {
        $this->id = $id;
        $this->tenantId = $tenantId;
        $this->balanceAmount = $balanceAmount;
        $this->balanceCurrency = $balanceCurrency;
        $this->initialAmount = $initialAmount;
        $this->initialCurrency = $initialCurrency;
        $this->status = $status;
        $this->createdAt = $createdAt;
        $this->expiresAt = $expiresAt;
        $this->cardNumber = $cardNumber;
        $this->pin = $pin;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateFromEvent(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
