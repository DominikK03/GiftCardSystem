<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Application\Handler;

use App\Application\Tenant\Command\CreateTenantCommand;
use App\Application\Tenant\Handler\CreateTenant;
use App\Application\Tenant\Port\TenantPersisterInterface;
use App\Domain\Tenant\Enum\TenantStatus;
use App\Domain\Tenant\ValueObject\TenantEmail;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Domain\Tenant\ValueObject\TenantName;
use PHPUnit\Framework\TestCase;

final class CreateTenantTest extends TestCase
{
    public function test_creates_new_tenant_with_generated_credentials(): void
    {
        $persister = $this->createMock(TenantPersisterInterface::class);
        $handler = new CreateTenant($persister);

        $command = new CreateTenantCommand(
            name: 'Test Company Ltd.',
            email: 'contact@testcompany.com',
            nip: '1234567890',
            street: 'ul. Testowa 123',
            city: 'Warszawa',
            postalCode: '00-001',
            country: 'Polska',
            phoneNumber: '+48123456789',
            representativeFirstName: 'Jan',
            representativeLastName: 'Kowalski'
        );

        $persister
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function ($tenant) {
                return $tenant->getName()->equals(TenantName::fromString('Test Company Ltd.'))
                    && $tenant->getEmail()->equals(TenantEmail::fromString('contact@testcompany.com'))
                    && $tenant->getStatus() === TenantStatus::ACTIVE
                    && strlen($tenant->getApiKey()->toString()) === 32
                    && strlen($tenant->getApiSecret()->toString()) === 64;
            }));

        $tenantId = $handler($command);

        $this->assertIsString($tenantId);
        $this->assertInstanceOf(TenantId::class, TenantId::fromString($tenantId));
    }
}
