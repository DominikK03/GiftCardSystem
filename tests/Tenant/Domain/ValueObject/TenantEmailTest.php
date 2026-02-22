<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Domain\ValueObject;

use App\Domain\Tenant\Exception\InvalidTenantEmailException;
use App\Domain\Tenant\ValueObject\TenantEmail;
use PHPUnit\Framework\TestCase;

final class TenantEmailTest extends TestCase
{
    public function test_can_create_from_valid_email(): void
    {
        $emailString = 'test@test.pl';
        $email = TenantEmail::fromString($emailString);
        $this->assertEquals($emailString, $email->toString());
    }

    public function test_cannot_create_from_invalid_email(): void
    {
        $invalidEmail = 'invalid-email';
        $this->expectException(InvalidTenantEmailException::class);
        TenantEmail::fromString($invalidEmail);
    }

    public function test_cannot_create_from_empty_string(): void
    {
        $this->expectException(InvalidTenantEmailException::class);
        TenantEmail::fromString('');
    }

    public function test_two_emails_with_same_value_are_equal(): void
    {
        $emailString = 'contact@tenant.com';
        $email1 = TenantEmail::fromString($emailString);
        $email2 = TenantEmail::fromString($emailString);

        $this->assertTrue($email1->equals($email2));
    }

    public function test_two_emails_with_different_values_are_not_equal(): void
    {
        $email1 = TenantEmail::fromString('contact1@tenant.com');
        $email2 = TenantEmail::fromString('contact2@tenant.com');

        $this->assertFalse($email1->equals($email2));
    }

    public function test_normalizes_email_to_lowercase(): void
    {
        $email = TenantEmail::fromString('Contact@TENANT.COM');
        $this->assertEquals('contact@tenant.com', $email->toString());
    }
}
