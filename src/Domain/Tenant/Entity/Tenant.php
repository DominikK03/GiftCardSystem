<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Entity;

use App\Domain\Tenant\Enum\TenantStatus;
use App\Domain\Tenant\ValueObject\Address;
use App\Domain\Tenant\ValueObject\ApiKey;
use App\Domain\Tenant\ValueObject\ApiSecret;
use App\Domain\Tenant\ValueObject\NIP;
use App\Domain\Tenant\ValueObject\PhoneNumber;
use App\Domain\Tenant\ValueObject\RepresentativeName;
use App\Domain\Tenant\ValueObject\TenantEmail;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Domain\Tenant\ValueObject\TenantName;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tenants')]
class Tenant
{
    #[ORM\Id]
    #[ORM\Column(type: 'tenant_id')]
    private TenantId $id;

    #[ORM\Column(type: 'tenant_name')]
    private TenantName $name;

    #[ORM\Column(type: 'tenant_email', unique: true)]
    private TenantEmail $email;

    #[ORM\Column(type: 'nip')]
    private NIP $nip;

    #[ORM\Embedded(class: Address::class)]
    private Address $address;

    #[ORM\Column(type: 'phone_number')]
    private PhoneNumber $phoneNumber;

    #[ORM\Embedded(class: RepresentativeName::class)]
    private RepresentativeName $representativeName;

    #[ORM\Column(type: 'api_key', unique: true)]
    private ApiKey $apiKey;

    #[ORM\Column(type: 'api_secret')]
    private ApiSecret $apiSecret;

    #[ORM\Column(type: 'string', enumType: TenantStatus::class)]
    private TenantStatus $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $suspendedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $allowedRedirectDomain = null;

    private function __construct(
        TenantId $id,
        TenantName $name,
        TenantEmail $email,
        NIP $nip,
        Address $address,
        PhoneNumber $phoneNumber,
        RepresentativeName $representativeName,
        ApiKey $apiKey,
        ApiSecret $apiSecret,
        TenantStatus $status,
        DateTimeImmutable $createdAt
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->nip = $nip;
        $this->address = $address;
        $this->phoneNumber = $phoneNumber;
        $this->representativeName = $representativeName;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->status = $status;
        $this->createdAt = $createdAt;
    }

    public static function create(
        TenantId $id,
        TenantName $name,
        TenantEmail $email,
        NIP $nip,
        Address $address,
        PhoneNumber $phoneNumber,
        RepresentativeName $representativeName,
        ApiKey $apiKey,
        ApiSecret $apiSecret
    ): self {
        return new self(
            $id,
            $name,
            $email,
            $nip,
            $address,
            $phoneNumber,
            $representativeName,
            $apiKey,
            $apiSecret,
            TenantStatus::ACTIVE,
            new DateTimeImmutable()
        );
    }

    public function suspend(): void
    {
        $this->status = TenantStatus::SUSPENDED;
        $this->suspendedAt = new DateTimeImmutable();
    }

    public function reactivate(): void
    {
        $this->status = TenantStatus::ACTIVE;
        $this->suspendedAt = null;
    }

    public function cancel(): void
    {
        $this->status = TenantStatus::CANCELLED;
        $this->cancelledAt = new DateTimeImmutable();
    }

    public function updateName(TenantName $name): void
    {
        $this->name = $name;
    }

    public function updateEmail(TenantEmail $email): void
    {
        $this->email = $email;
    }

    public function updateAddress(Address $address): void
    {
        $this->address = $address;
    }

    public function updatePhoneNumber(PhoneNumber $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }

    public function updateRepresentativeName(RepresentativeName $name): void
    {
        $this->representativeName = $name;
    }

    public function regenerateApiCredentials(ApiKey $apiKey, ApiSecret $apiSecret): void
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    public function getId(): TenantId
    {
        return $this->id;
    }

    public function getName(): TenantName
    {
        return $this->name;
    }

    public function getEmail(): TenantEmail
    {
        return $this->email;
    }

    public function getNIP(): NIP
    {
        return $this->nip;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getPhoneNumber(): PhoneNumber
    {
        return $this->phoneNumber;
    }

    public function getRepresentativeName(): RepresentativeName
    {
        return $this->representativeName;
    }

    public function getApiKey(): ApiKey
    {
        return $this->apiKey;
    }

    public function getApiSecret(): ApiSecret
    {
        return $this->apiSecret;
    }

    public function getStatus(): TenantStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSuspendedAt(): ?DateTimeImmutable
    {
        return $this->suspendedAt;
    }

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function getAllowedRedirectDomain(): ?string
    {
        return $this->allowedRedirectDomain;
    }

    public function updateAllowedRedirectDomain(string $domain): void
    {
        $this->allowedRedirectDomain = $domain;
    }
}
