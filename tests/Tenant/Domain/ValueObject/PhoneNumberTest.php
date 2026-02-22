<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Domain\ValueObject;

use App\Domain\Tenant\Exception\InvalidPhoneNumberException;
use App\Domain\Tenant\ValueObject\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    public function test_can_create_from_valid_polish_phone(): void
    {
        $phone = PhoneNumber::fromString('+48123456789');

        $this->assertEquals('+48123456789', $phone->toString());
    }

    public function test_normalizes_phone_number(): void
    {
        $phone = PhoneNumber::fromString(' +48 123 456 789 ');

        $this->assertEquals('+48123456789', $phone->toString());
    }

    public function test_cannot_create_from_empty_string(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        PhoneNumber::fromString('');
    }

    public function test_cannot_create_from_too_short_number(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        PhoneNumber::fromString('+4812');
    }

    public function test_cannot_create_from_invalid_format(): void
    {
        $this->expectException(InvalidPhoneNumberException::class);
        PhoneNumber::fromString('abc123def');
    }

    public function test_two_phones_with_same_value_are_equal(): void
    {
        $phone1 = PhoneNumber::fromString('+48123456789');
        $phone2 = PhoneNumber::fromString('+48 123 456 789');

        $this->assertTrue($phone1->equals($phone2));
    }
}
