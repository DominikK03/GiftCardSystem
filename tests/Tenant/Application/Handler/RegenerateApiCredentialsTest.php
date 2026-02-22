<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Application\Handler;

use App\Application\Tenant\Command\RegenerateApiCredentialsCommand;
use App\Application\Tenant\Handler\RegenerateApiCredentials;
use App\Application\Tenant\Port\TenantPersisterInterface;
use App\Application\Tenant\Port\TenantProviderInterface;
use App\Domain\Tenant\Entity\Tenant;
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

final class RegenerateApiCredentialsTest extends TestCase
{
    public function test_regenerates_api_credentials(): void
    {
        $tenantId = TenantId::generate();
        $originalApiKey = ApiKey::generate();
        $originalApiSecret = ApiSecret::generate();

        $tenant = Tenant::create(
            id: $tenantId,
            name: TenantName::fromString('Test Company Ltd.'),
            email: TenantEmail::fromString('contact@testcompany.com'),
            nip: NIP::fromString('1234567890'),
            address: Address::create('ul. Testowa 123', 'Warszawa', '00-001', 'Polska'),
            phoneNumber: PhoneNumber::fromString('+48123456789'),
            representativeName: RepresentativeName::create('Jan', 'Kowalski'),
            apiKey: $originalApiKey,
            apiSecret: $originalApiSecret
        );

        $originalKeyValue = $originalApiKey->toString();
        $originalSecretValue = $originalApiSecret->toString();

        $provider = $this->createMock(TenantProviderInterface::class);
        $provider
            ->expects($this->once())
            ->method('loadById')
            ->with($this->callback(fn($id) => $id->equals($tenantId)))
            ->willReturn($tenant);

        $persister = $this->createMock(TenantPersisterInterface::class);
        $persister
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function ($savedTenant) use ($originalKeyValue, $originalSecretValue) {
                $newApiKey = $savedTenant->getApiKey()->toString();
                $newApiSecret = $savedTenant->getApiSecret()->toString();

                return $newApiKey !== $originalKeyValue
                    && $newApiSecret !== $originalSecretValue
                    && strlen($newApiKey) === 32
                    && strlen($newApiSecret) === 64;
            }));

        $handler = new RegenerateApiCredentials($provider, $persister);
        $command = new RegenerateApiCredentialsCommand(tenantId: $tenantId->toString());

        $handler($command);
    }
}
