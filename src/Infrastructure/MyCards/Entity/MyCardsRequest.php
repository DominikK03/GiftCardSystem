<?php

declare(strict_types=1);

namespace App\Infrastructure\MyCards\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'my_cards_requests')]
class MyCardsRequest
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $customerEmail;

    #[ORM\Column(type: 'string', length: 6)]
    private string $verificationCode;

    #[ORM\Column(type: 'boolean')]
    private bool $verified = false;

    #[ORM\Column(type: 'string', length: 30)]
    private string $status = 'pending_verification';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    public function __construct(string $customerEmail, string $verificationCode)
    {
        $this->id = Uuid::uuid4()->toString();
        $this->customerEmail = $customerEmail;
        $this->verificationCode = $verificationCode;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify('+15 minutes');
    }

    public function getId(): string { return $this->id; }
    public function getCustomerEmail(): string { return $this->customerEmail; }
    public function getVerificationCode(): string { return $this->verificationCode; }
    public function isVerified(): bool { return $this->verified; }

    public function isExpired(): bool
    {
        return new \DateTimeImmutable() > $this->expiresAt;
    }

    public function verify(): void
    {
        $this->verified = true;
        $this->status = 'verified';
    }

    public function markExpired(): void
    {
        $this->status = 'expired';
    }
}
