<?php

declare(strict_types=1);

namespace App\Tests\User\Application\Handler;

use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\Handler\RegisterUser;
use App\Application\User\Port\UserPersisterInterface;
use App\Domain\User\Entity\User;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Domain\User\Port\UserRepository;
use App\Domain\User\ValueObject\UserEmail;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class RegisterUserTest extends TestCase
{
    private const EMAIL = 'admin@example.com';
    private const PASSWORD = 'secret123';
    private const ROLE = 'OWNER';

    public function test_it_registers_new_user(): void
    {
        $persister = $this->createMock(UserPersisterInterface::class);
        $repository = $this->createMock(UserRepository::class);
        $passwordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);
        $passwordHasher = $this->createMock(PasswordHasherInterface::class);

        $repository
            ->expects($this->once())
            ->method('existsByEmail')
            ->with($this->callback(fn($email) => $email instanceof UserEmail && $email->toString() === self::EMAIL))
            ->willReturn(false);

        $passwordHasherFactory
            ->expects($this->once())
            ->method('getPasswordHasher')
            ->with(User::class)
            ->willReturn($passwordHasher);

        $passwordHasher
            ->expects($this->once())
            ->method('hash')
            ->with(self::PASSWORD)
            ->willReturn('$hashed$password');

        $persister
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(fn($user) => $user instanceof User));

        $handler = new RegisterUser($persister, $repository, $passwordHasherFactory);
        $command = new RegisterUserCommand(self::EMAIL, self::PASSWORD, self::ROLE);

        $userId = $handler($command);

        $this->assertIsString($userId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $userId);
    }

    public function test_it_throws_exception_when_email_already_exists(): void
    {
        $persister = $this->createMock(UserPersisterInterface::class);
        $repository = $this->createMock(UserRepository::class);
        $passwordHasherFactory = $this->createMock(PasswordHasherFactoryInterface::class);

        $repository
            ->expects($this->once())
            ->method('existsByEmail')
            ->willReturn(true);

        $passwordHasherFactory->expects($this->never())->method('getPasswordHasher');
        $persister->expects($this->never())->method('handle');

        $handler = new RegisterUser($persister, $repository, $passwordHasherFactory);
        $command = new RegisterUserCommand(self::EMAIL, self::PASSWORD, self::ROLE);

        $this->expectException(UserAlreadyExistsException::class);
        $this->expectExceptionMessage('User with email admin@example.com already exists');

        $handler($command);
    }
}
