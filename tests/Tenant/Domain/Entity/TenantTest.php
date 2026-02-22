<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Domain\Entity;

use App\Domain\Tenant\Entity\Tenant;
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
use PHPUnit\Framework\TestCase;

final class TenantTest extends TestCase
{
    public function test_can_create_tenant(): void
    {
        $id = TenantId::generate();
        $name = TenantName::fromString('Test Company Ltd.');
        $email = TenantEmail::fromString('contact@testcompany.com');
        $nip = NIP::fromString('1234567890');
        $address = Address::create('ul. Testowa 123', 'Warszawa', '00-001', 'Polska');
        $phone = PhoneNumber::fromString('+48123456789');
        $representative = RepresentativeName::create('Jan', 'Kowalski');
        $apiKey = ApiKey::generate();
        $apiSecret = ApiSecret::generate();

        $tenant = Tenant::create(
            $id,
            $name,
            $email,
            $nip,
            $address,
            $phone,
            $representative,
            $apiKey,
            $apiSecret
        );

        $this->assertEquals($id, $tenant->getId());
        $this->assertEquals($name, $tenant->getName());
        $this->assertEquals($email, $tenant->getEmail());
        $this->assertEquals($nip, $tenant->getNIP());
        $this->assertEquals($address, $tenant->getAddress());
        $this->assertEquals($phone, $tenant->getPhoneNumber());
        $this->assertEquals($representative, $tenant->getRepresentativeName());
        $this->assertEquals($apiKey, $tenant->getApiKey());
        $this->assertEquals($apiSecret, $tenant->getApiSecret());
        $this->assertEquals(TenantStatus::ACTIVE, $tenant->getStatus());
        $this->assertNotNull($tenant->getCreatedAt());
    }

    public function test_can_suspend_active_tenant(): void
    {
        $tenant = $this->createTenant();

        $tenant->suspend();

        $this->assertEquals(TenantStatus::SUSPENDED, $tenant->getStatus());
        $this->assertNotNull($tenant->getSuspendedAt());
    }

    public function test_can_reactivate_suspended_tenant(): void
    {
        $tenant = $this->createTenant();
        $tenant->suspend();

        $tenant->reactivate();

        $this->assertEquals(TenantStatus::ACTIVE, $tenant->getStatus());
        $this->assertNull($tenant->getSuspendedAt());
    }

    public function test_can_cancel_tenant(): void
    {
        $tenant = $this->createTenant();

        $tenant->cancel();

        $this->assertEquals(TenantStatus::CANCELLED, $tenant->getStatus());
        $this->assertNotNull($tenant->getCancelledAt());
    }

    public function test_can_update_name(): void
    {
        $tenant = $this->createTenant();
        $newName = TenantName::fromString('New Company Name');

        $tenant->updateName($newName);

        $this->assertEquals($newName, $tenant->getName());
    }

    public function test_can_update_email(): void
    {
        $tenant = $this->createTenant();
        $newEmail = TenantEmail::fromString('newemail@company.com');

        $tenant->updateEmail($newEmail);

        $this->assertEquals($newEmail, $tenant->getEmail());
    }

    public function test_can_update_address(): void
    {
        $tenant = $this->createTenant();
        $newAddress = Address::create('ul. Nowa 456', 'Kraków', '30-001', 'Polska');

        $tenant->updateAddress($newAddress);

        $this->assertEquals($newAddress, $tenant->getAddress());
    }

    public function test_can_update_phone_number(): void
    {
        $tenant = $this->createTenant();
        $newPhone = PhoneNumber::fromString('+48987654321');

        $tenant->updatePhoneNumber($newPhone);

        $this->assertEquals($newPhone, $tenant->getPhoneNumber());
    }

    public function test_can_update_representative_name(): void
    {
        $tenant = $this->createTenant();
        $newRepresentative = RepresentativeName::create('Anna', 'Nowak');

        $tenant->updateRepresentativeName($newRepresentative);

        $this->assertEquals($newRepresentative, $tenant->getRepresentativeName());
    }

    public function test_can_regenerate_api_credentials(): void
    {
        $tenant = $this->createTenant();
        $oldApiKey = $tenant->getApiKey();
        $oldApiSecret = $tenant->getApiSecret();

        $newApiKey = ApiKey::generate();
        $newApiSecret = ApiSecret::generate();

        $tenant->regenerateApiCredentials($newApiKey, $newApiSecret);

        $this->assertEquals($newApiKey, $tenant->getApiKey());
        $this->assertEquals($newApiSecret, $tenant->getApiSecret());
        $this->assertNotEquals($oldApiKey, $tenant->getApiKey());
        $this->assertNotEquals($oldApiSecret, $tenant->getApiSecret());
    }

    private function createTenant(): Tenant
    {
        return Tenant::create(
            TenantId::generate(),
            TenantName::fromString('Test Company'),
            TenantEmail::fromString('test@company.com'),
            NIP::fromString('1234567890'),
            Address::create('ul. Testowa 1', 'Warszawa', '00-001', 'Polska'),
            PhoneNumber::fromString('+48123456789'),
            RepresentativeName::create('Jan', 'Kowalski'),
            ApiKey::generate(),
            ApiSecret::generate()
        );
    }
}
