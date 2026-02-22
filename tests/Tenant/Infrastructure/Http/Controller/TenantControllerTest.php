<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Infrastructure\Http\Controller;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TenantControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->connection = static::getContainer()->get(Connection::class);

        $this->ensureSchema();
        $this->truncateTables();
    }

    public function test_create_returns_201_and_id(): void
    {
        $this->client->request(
            'POST',
            '/api/tenants',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Test Company Ltd.',
                'email' => 'contact@testcompany.com',
                'nip' => '1234567890',
                'street' => 'ul. Testowa 123',
                'city' => 'Warszawa',
                'postalCode' => '00-001',
                'country' => 'Polska',
                'phoneNumber' => '+48123456789',
                'representativeFirstName' => 'Jan',
                'representativeLastName' => 'Kowalski'
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $payload = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame('Tenant created successfully', $payload['message']);
        $this->assertTrue(Uuid::isValid($payload['id']));
    }

    public function test_create_returns_400_when_missing_required_fields(): void
    {
        $this->client->request(
            'POST',
            '/api/tenants',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Test Company Ltd.',
            ])
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_create_returns_400_when_invalid_json(): void
    {
        $this->client->request(
            'POST',
            '/api/tenants',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{invalid'
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_create_returns_400_when_email_is_invalid(): void
    {
        $this->client->request(
            'POST',
            '/api/tenants',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Test Company Ltd.',
                'email' => 'not-an-email',
                'nip' => '1234567890',
                'street' => 'ul. Testowa 123',
                'city' => 'Warszawa',
                'postalCode' => '00-001',
                'country' => 'Polska',
                'phoneNumber' => '+48123456789',
                'representativeFirstName' => 'Jan',
                'representativeLastName' => 'Kowalski'
            ])
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_create_returns_400_when_nip_is_invalid(): void
    {
        $this->client->request(
            'POST',
            '/api/tenants',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Test Company Ltd.',
                'email' => 'contact@testcompany.com',
                'nip' => '123',
                'street' => 'ul. Testowa 123',
                'city' => 'Warszawa',
                'postalCode' => '00-001',
                'country' => 'Polska',
                'phoneNumber' => '+48123456789',
                'representativeFirstName' => 'Jan',
                'representativeLastName' => 'Kowalski'
            ])
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_suspend_returns_200(): void
    {
        $tenantId = $this->createTenant();

        $this->client->request(
            'POST',
            "/api/tenants/{$tenantId}/suspend",
            server: ['CONTENT_TYPE' => 'application/json']
        );

        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Tenant suspended successfully', $payload['message']);
    }

    public function test_suspend_returns_400_when_uuid_is_invalid(): void
    {
        $this->client->request(
            'POST',
            '/api/tenants/invalid-uuid/suspend',
            server: ['CONTENT_TYPE' => 'application/json']
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_reactivate_returns_200(): void
    {
        $tenantId = $this->createTenant();
        $this->client->request('POST', "/api/tenants/{$tenantId}/suspend");

        $this->client->request(
            'POST',
            "/api/tenants/{$tenantId}/reactivate",
            server: ['CONTENT_TYPE' => 'application/json']
        );

        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Tenant reactivated successfully', $payload['message']);
    }

    public function test_cancel_returns_200(): void
    {
        $tenantId = $this->createTenant();

        $this->client->request(
            'POST',
            "/api/tenants/{$tenantId}/cancel",
            server: ['CONTENT_TYPE' => 'application/json']
        );

        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Tenant cancelled successfully', $payload['message']);
    }

    public function test_regenerate_credentials_returns_200(): void
    {
        $tenantId = $this->createTenant();

        $this->client->request(
            'POST',
            "/api/tenants/{$tenantId}/regenerate-credentials",
            server: ['CONTENT_TYPE' => 'application/json']
        );

        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Credentials regenerated successfully', $payload['message']);
    }

    public function test_get_returns_200_and_tenant_details(): void
    {
        $tenantId = $this->createTenant();

        $this->client->request('GET', "/api/tenants/{$tenantId}");

        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame($tenantId, $payload['id']);
        $this->assertSame('Test Company Ltd.', $payload['name']);
        $this->assertSame('contact@testcompany.com', $payload['email']);
        $this->assertSame('1234567890', $payload['nip']);
        $this->assertSame('ACTIVE', $payload['status']);
        $this->assertArrayHasKey('address', $payload);
        $this->assertArrayHasKey('representative', $payload);
        $this->assertArrayHasKey('apiKey', $payload);
        $this->assertArrayNotHasKey('apiSecret', $payload);
    }

    public function test_get_returns_400_when_uuid_is_invalid(): void
    {
        $this->client->request('GET', '/api/tenants/invalid-uuid');

        $this->assertResponseStatusCodeSame(400);
    }

    public function test_list_returns_200_and_paginated_tenants(): void
    {
        $tenantId1 = $this->createTenant();

        $this->client->request(
            'POST',
            '/api/tenants',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Another Company',
                'email' => 'another@company.com',
                'nip' => '9876543210',
                'street' => 'ul. Inna 456',
                'city' => 'Kraków',
                'postalCode' => '30-001',
                'country' => 'Polska',
                'phoneNumber' => '+48987654321',
                'representativeFirstName' => 'Anna',
                'representativeLastName' => 'Nowak'
            ])
        );
        $payload2 = json_decode($this->client->getResponse()->getContent(), true);
        $tenantId2 = $payload2['id'];

        $this->client->request('GET', '/api/tenants');

        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('tenants', $payload);
        $this->assertArrayHasKey('total', $payload);
        $this->assertArrayHasKey('page', $payload);
        $this->assertArrayHasKey('limit', $payload);
        $this->assertArrayHasKey('totalPages', $payload);

        $this->assertSame(2, $payload['total']);
        $this->assertSame(1, $payload['page']);
        $this->assertSame(20, $payload['limit']);
        $this->assertCount(2, $payload['tenants']);
    }

    public function test_list_supports_pagination(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->client->request(
                'POST',
                '/api/tenants',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode([
                    'name' => "Company {$i}",
                    'email' => "company{$i}@test.com",
                    'nip' => str_pad((string)$i, 10, '0', STR_PAD_LEFT),
                    'street' => "ul. Test {$i}",
                    'city' => 'Warszawa',
                    'postalCode' => '00-001',
                    'country' => 'Polska',
                    'phoneNumber' => "+4812345678{$i}",
                    'representativeFirstName' => 'Jan',
                    'representativeLastName' => 'Kowalski'
                ])
            );
        }

        $this->client->request('GET', '/api/tenants?page=2&limit=2');

        $this->assertResponseStatusCodeSame(200);
        $payload = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame(3, $payload['total']);
        $this->assertSame(2, $payload['page']);
        $this->assertSame(2, $payload['limit']);
        $this->assertSame(2, $payload['totalPages']);
        $this->assertCount(1, $payload['tenants']);
    }

    private function createTenant(): string
    {
        $this->client->request(
            'POST',
            '/api/tenants',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Test Company Ltd.',
                'email' => 'contact@testcompany.com',
                'nip' => '1234567890',
                'street' => 'ul. Testowa 123',
                'city' => 'Warszawa',
                'postalCode' => '00-001',
                'country' => 'Polska',
                'phoneNumber' => '+48123456789',
                'representativeFirstName' => 'Jan',
                'representativeLastName' => 'Kowalski'
            ])
        );

        $payload = json_decode($this->client->getResponse()->getContent(), true);
        return $payload['id'];
    }

    private function ensureSchema(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['tenants'])) {
        }
    }

    private function truncateTables(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->tablesExist(['tenants'])) {
            $this->connection->executeStatement('TRUNCATE TABLE tenants CASCADE');
        }
    }
}
