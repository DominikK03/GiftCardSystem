<?php

declare(strict_types=1);

namespace App\Tests\User\Application\Handler;

use App\Application\User\Handler\GetUser;
use App\Application\User\Query\GetUserQuery;
use App\Domain\User\Entity\User;
use App\Domain\User\Port\UserRepository;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use PHPUnit\Framework\TestCase;

final class GetUserTest extends TestCase
{
    public function test_it_returns_null_when_user_not_found(): void
    {
        $repository = $this->createMock(UserRepository::class);
        $repository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $handler = new GetUser($repository);
        $missingId = UserId::generate()->toString();
        $query = new GetUserQuery($missingId);

        $result = $handler($query);

        $this->assertNull($result);
    }

    public function test_it_returns_user_view_when_found(): void
    {
        $repository = $this->createMock(UserRepository::class);

        $userId = UserId::generate();
        $user = User::register(
            $userId,
            UserEmail::fromString('admin@example.com'),
            '$hashed',
            UserRole::OWNER
        );

        $repository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($user);

        $handler = new GetUser($repository);
        $query = new GetUserQuery($userId->toString());

        $result = $handler($query);

        $this->assertNotNull($result);
        $this->assertSame($userId->toString(), $result->id);
        $this->assertSame('admin@example.com', $result->email);
        $this->assertSame('OWNER', $result->role);
        $this->assertTrue($result->isActive);
    }
}
