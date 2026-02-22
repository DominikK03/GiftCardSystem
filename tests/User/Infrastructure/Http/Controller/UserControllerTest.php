<?php

declare(strict_types=1);

namespace App\Tests\User\Infrastructure\Http\Controller;

use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class UserControllerTest extends WebTestCase
{
    public function test_register_returns_201_with_user_id(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE users CASCADE');

        $client->request('POST', '/api/users', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'owner@example.com',
            'password' => 'secret123',
            'role' => 'OWNER'
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $data['id']);
    }

    public function test_register_fails_when_email_already_exists(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE users CASCADE');
        $this->createUser('existing@example.com', UserRole::OWNER);

        $client->request('POST', '/api/users', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'existing@example.com',
            'password' => 'secret123',
            'role' => 'ADMIN'
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function test_get_returns_200_with_user_details(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE users CASCADE');
        $userId = $this->createUser('admin@example.com', UserRole::ADMIN);
        $client->request('GET', "/api/users/{$userId}");

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($userId, $data['id']);
        $this->assertSame('admin@example.com', $data['email']);
        $this->assertSame('ADMIN', $data['role']);
        $this->assertTrue($data['isActive']);
    }

    public function test_get_returns_404_when_user_not_found(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE users CASCADE');
        $client->request('GET', '/api/users/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_list_returns_200_with_paginated_users(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE users CASCADE');
        $this->createUser('user1@example.com', UserRole::OWNER);
        $this->createUser('user2@example.com', UserRole::ADMIN);
        $this->createUser('user3@example.com', UserRole::SUPPORT);
        $client->request('GET', '/api/users?page=1&limit=2');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('users', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('limit', $data);
        $this->assertArrayHasKey('totalPages', $data);

        $this->assertCount(2, $data['users']);
        $this->assertSame(3, $data['total']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(2, $data['limit']);
        $this->assertSame(2, $data['totalPages']);
    }

    public function test_change_role_returns_200(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE users CASCADE');
        $userId = $this->createUser('user@example.com', UserRole::SUPPORT);
        $client->request('PUT', "/api/users/{$userId}/role", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'role' => 'ADMIN'
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $client->request('GET', "/api/users/{$userId}");
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('ADMIN', $data['role']);
    }

    public function test_deactivate_returns_200(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE users CASCADE');
        $userId = $this->createUser('user@example.com', UserRole::ADMIN);
        $client->request('PUT', "/api/users/{$userId}/deactivate");

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $client->request('GET', "/api/users/{$userId}");
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['isActive']);
        $this->assertNotNull($data['deactivatedAt']);
    }

    public function test_activate_returns_200(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE users CASCADE');
        $userId = $this->createUser('user@example.com', UserRole::ADMIN);

        $client->request('PUT', "/api/users/{$userId}/deactivate");

        $client->request('PUT', "/api/users/{$userId}/activate");

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $client->request('GET', "/api/users/{$userId}");
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['isActive']);
        $this->assertNull($data['deactivatedAt']);
    }

    public function test_change_password_returns_200(): void
    {
        $client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->getConnection()->executeStatement('TRUNCATE TABLE users CASCADE');
        $userId = $this->createUser('user@example.com', UserRole::ADMIN);
        $client->request('PUT', "/api/users/{$userId}/password", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'password' => 'newsecret456'
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    private function createUser(string $email, UserRole $role): string
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = User::register(
            UserId::generate(),
            UserEmail::fromString($email),
            '$2y$13$hashedpassword',
            $role
        );

        $entityManager->persist($user);
        $entityManager->flush();

        return $user->getId()->toString();
    }
}
