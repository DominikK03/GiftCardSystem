<?php

declare(strict_types=1);

namespace App\Infrastructure\Activation\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'activation_requests')]
#[ORM\Index(columns: ['card_number'], name: 'idx_activation_card_number')]
#[ORM\Index(columns: ['status'], name: 'idx_activation_status')]
class ActivationRequest
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 12)]
    private string $cardNumber;

    #[ORM\Column(type: 'string', length: 255)]
    private string $customerEmail;

    #[ORM\Column(type: 'string', length: 6)]
    private string $verificationCode;

    #[ORM\Column(type: 'boolean')]
    private bool $verified = false;

    #[ORM\Column(type: 'string', length: 36)]
    private string $giftCardId;

    #[ORM\Column(type: 'string', length: 36)]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 2048)]
    private string $returnUrl;

    #[ORM\Column(type: 'string', length: 2048, nullable: true)]
    private ?string $callbackUrl;

    #[ORM\Column(type: 'string', length: 30)]
    private string $status = 'pending_verification';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    public function __construct(
        string $cardNumber,
        string $customerEmail,
        string $verificationCode,
        string $giftCardId,
        string $tenantId,
        string $returnUrl,
        ?string $callbackUrl = null
    ) {
        $this->id = Uuid::uuid4()->toString();
        $this->cardNumber = $cardNumber;
        $this->customerEmail = $customerEmail;
        $this->verificationCode = $verificationCode;
        $this->giftCardId = $giftCardId;
        $this->tenantId = $tenantId;
        $this->returnUrl = $returnUrl;
        $this->callbackUrl = $callbackUrl;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify('+15 minutes');
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCardNumber(): string
    {
        return $this->cardNumber;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function getVerificationCode(): string
    {
        return $this->verificationCode;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function getGiftCardId(): string
    {
        return $this->giftCardId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    public function getCallbackUrl(): ?string
    {
        return $this->callbackUrl;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return new \DateTimeImmutable() > $this->expiresAt;
    }

    public function verify(): void
    {
        $this->verified = true;
        $this->status = 'verified';
    }

    public function complete(): void
    {
        $this->status = 'completed';
    }

    public function markExpired(): void
    {
        $this->status = 'expired';
    }
}
