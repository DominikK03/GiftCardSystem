<?php

declare(strict_types=1);

namespace App\Infrastructure\Activation\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'card_assignments')]
class CardAssignment
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $giftCardId;

    #[ORM\Column(type: 'string', length: 36)]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $customerEmail;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $assignedAt;

    public function __construct(
        string $giftCardId,
        string $tenantId,
        string $customerEmail
    ) {
        $this->id = Uuid::uuid4()->toString();
        $this->giftCardId = $giftCardId;
        $this->tenantId = $tenantId;
        $this->customerEmail = $customerEmail;
        $this->assignedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getGiftCardId(): string
    {
        return $this->giftCardId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function getAssignedAt(): \DateTimeImmutable
    {
        return $this->assignedAt;
    }
}
