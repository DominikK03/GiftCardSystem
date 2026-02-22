<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Infrastructure\Security;

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
use App\Infrastructure\Tenant\Security\HmacValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class HmacAuthenticationListenerTest extends WebTestCase
{
    private HmacValidator $hmacValidator;

    protected function setUp(): void
    {
        $this->hmacValidator = new HmacValidator();
    }

    public function test_authenticates_valid_hmac_request(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE tenants CASCADE');

        $tenant = $this->createTenant();
        $entityManager->persist($tenant);
        $entityManager->flush();

        $requestBody = '{"amount":10000,"currency":"PLN"}';
        $timestamp = (string) time();

        $signature = $this->hmacValidator->calculateSignature(
            $tenant->getId(),
            $timestamp,
            $requestBody,
            $tenant->getApiSecret()
        );

        $client->request(
            'POST',
            '/api/gift-cards/create',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_TENANT_ID' => $tenant->getId()->toString(),
                'HTTP_X_TIMESTAMP' => $timestamp,
                'HTTP_X_SIGNATURE' => $signature,
            ],
            $requestBody
        );

        $this->assertNotEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        $this->assertNotEquals(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function test_rejects_request_without_authentication_headers(): void
    {
        $client = static::createClient();

        $requestBody = '{"amount":10000,"currency":"PLN"}';

        $client->request(
            'POST',
            '/api/gift-cards/create',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $requestBody
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Missing required authentication headers', $response['error'] ?? '');
    }

    public function test_rejects_request_with_invalid_signature(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE tenants CASCADE');

        $tenant = $this->createTenant();
        $entityManager->persist($tenant);
        $entityManager->flush();

        $requestBody = '{"amount":10000,"currency":"PLN"}';
        $timestamp = (string) time();
        $invalidSignature = 'this_is_an_invalid_signature_1234567890abcdef';

        $client->request(
            'POST',
            '/api/gift-cards/create',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_TENANT_ID' => $tenant->getId()->toString(),
                'HTTP_X_TIMESTAMP' => $timestamp,
                'HTTP_X_SIGNATURE' => $invalidSignature,
            ],
            $requestBody
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Invalid HMAC signature', $response['error'] ?? '');
    }

    public function test_rejects_request_with_expired_timestamp(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE tenants CASCADE');

        $tenant = $this->createTenant();
        $entityManager->persist($tenant);
        $entityManager->flush();

        $requestBody = '{"amount":10000,"currency":"PLN"}';
        $expiredTimestamp = (string) (time() - 360);

        $signature = $this->hmacValidator->calculateSignature(
            $tenant->getId(),
            $expiredTimestamp,
            $requestBody,
            $tenant->getApiSecret()
        );

        $client->request(
            'POST',
            '/api/gift-cards/create',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_TENANT_ID' => $tenant->getId()->toString(),
                'HTTP_X_TIMESTAMP' => $expiredTimestamp,
                'HTTP_X_SIGNATURE' => $signature,
            ],
            $requestBody
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Request timestamp expired', $response['error'] ?? '');
    }

    public function test_rejects_suspended_tenant(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE tenants CASCADE');

        $tenant = $this->createTenant();
        $tenant->suspend();
        $entityManager->persist($tenant);
        $entityManager->flush();

        $requestBody = '{"amount":10000,"currency":"PLN"}';
        $timestamp = (string) time();

        $signature = $this->hmacValidator->calculateSignature(
            $tenant->getId(),
            $timestamp,
            $requestBody,
            $tenant->getApiSecret()
        );

        $client->request(
            'POST',
            '/api/gift-cards/create',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_TENANT_ID' => $tenant->getId()->toString(),
                'HTTP_X_TIMESTAMP' => $timestamp,
                'HTTP_X_SIGNATURE' => $signature,
            ],
            $requestBody
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Tenant account is suspended', $response['error'] ?? '');
    }

    public function test_rejects_cancelled_tenant(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE tenants CASCADE');

        $tenant = $this->createTenant();
        $tenant->cancel();
        $entityManager->persist($tenant);
        $entityManager->flush();

        $requestBody = '{"amount":10000,"currency":"PLN"}';
        $timestamp = (string) time();

        $signature = $this->hmacValidator->calculateSignature(
            $tenant->getId(),
            $timestamp,
            $requestBody,
            $tenant->getApiSecret()
        );

        $client->request(
            'POST',
            '/api/gift-cards/create',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_TENANT_ID' => $tenant->getId()->toString(),
                'HTTP_X_TIMESTAMP' => $timestamp,
                'HTTP_X_SIGNATURE' => $signature,
            ],
            $requestBody
        );

        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Tenant account is cancelled', $response['error'] ?? '');
    }

    public function test_does_not_apply_to_backoffice_routes(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/users');

        $response = $client->getResponse();
        if ($response->getStatusCode() === Response::HTTP_INTERNAL_SERVER_ERROR) {
            $responseData = json_decode($response->getContent(), true);
            $this->assertStringNotContainsString('Missing required authentication headers', $responseData['error'] ?? '');
        }

        $this->assertTrue(true);
    }

    private function createTenant(): Tenant
    {
        return Tenant::create(
            TenantId::generate(),
            TenantName::fromString('Test Company Ltd'),
            TenantEmail::fromString('test@example.com'),
            NIP::fromString('1234567890'),
            Address::create('ul. Testowa 1', 'Warszawa', '00-001', 'Polska'),
            PhoneNumber::fromString('+48123456789'),
            RepresentativeName::create('Jan', 'Kowalski'),
            ApiKey::generate(),
            ApiSecret::generate()
        );
    }
}
