<?php

declare(strict_types=1);

namespace App\Tests\User\Application\Handler;

use App\Application\User\Command\ChangeUserRoleCommand;
use App\Application\User\Handler\ChangeUserRole;
use App\Application\User\Port\UserPersisterInterface;
use App\Application\User\Port\UserProviderInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use PHPUnit\Framework\TestCase;

final class ChangeUserRoleTest extends TestCase
{
    public function test_it_changes_user_role(): void
    {
        $provider = $this->createMock(UserProviderInterface::class);
        $persister = $this->createMock(UserPersisterInterface::class);

        $userId = UserId::generate();
        $user = User::register(
            $userId,
            UserEmail::fromString('admin@example.com'),
            '$hashed',
            UserRole::SUPPORT
        );

        $provider
            ->expects($this->once())
            ->method('loadFromId')
            ->with($this->callback(fn($id) => $id->equals($userId)))
            ->willReturn($user);

        $persister
            ->expects($this->once())
            ->method('handle')
            ->with($user);

        $handler = new ChangeUserRole($provider, $persister);
        $command = new ChangeUserRoleCommand($userId->toString(), 'ADMIN');

        $handler($command);

        $this->assertSame(UserRole::ADMIN, $user->getRole());
    }
}
