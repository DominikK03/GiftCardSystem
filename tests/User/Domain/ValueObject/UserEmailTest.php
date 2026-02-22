<?php

declare(strict_types=1);

namespace App\Tests\User\Domain\ValueObject;

use App\Domain\User\Exception\InvalidUserEmailException;
use App\Domain\User\ValueObject\UserEmail;
use PHPUnit\Framework\TestCase;

final class UserEmailTest extends TestCase
{
    public function test_it_creates_email_from_valid_string(): void
    {
        $email = UserEmail::fromString('admin@example.com');

        $this->assertSame('admin@example.com', $email->toString());
    }

    public function test_it_normalizes_email_to_lowercase(): void
    {
        $email = UserEmail::fromString('Admin@EXAMPLE.COM');

        $this->assertSame('admin@example.com', $email->toString());
    }

    public function test_it_trims_whitespace(): void
    {
        $email = UserEmail::fromString('  admin@example.com  ');

        $this->assertSame('admin@example.com', $email->toString());
    }

    public function test_it_throws_exception_for_empty_email(): void
    {
        $this->expectException(InvalidUserEmailException::class);
        $this->expectExceptionMessage('User email cannot be empty');

        UserEmail::fromString('');
    }

    public function test_it_throws_exception_for_invalid_format(): void
    {
        $this->expectException(InvalidUserEmailException::class);
        $this->expectExceptionMessage('Invalid email format');

        UserEmail::fromString('not-an-email');
    }

    public function test_it_compares_emails(): void
    {
        $email1 = UserEmail::fromString('admin@example.com');
        $email2 = UserEmail::fromString('admin@example.com');
        $email3 = UserEmail::fromString('other@example.com');

        $this->assertTrue($email1->equals($email2));
        $this->assertFalse($email1->equals($email3));
    }
}
