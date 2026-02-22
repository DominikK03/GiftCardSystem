<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Domain\ValueObject;

use App\Domain\Tenant\Exception\InvalidNIPException;
use App\Domain\Tenant\ValueObject\NIP;
use PHPUnit\Framework\TestCase;

final class NIPTest extends TestCase
{
    public function test_can_create_from_valid_nip_with_dashes(): void
    {
        $nip = NIP::fromString('123-456-78-90');

        $this->assertEquals('1234567890', $nip->toString());
    }

    public function test_can_create_from_valid_nip_without_dashes(): void
    {
        $nip = NIP::fromString('1234567890');

        $this->assertEquals('1234567890', $nip->toString());
    }

    public function test_cannot_create_from_invalid_length(): void
    {
        $this->expectException(InvalidNIPException::class);
        NIP::fromString('123456789');
    }

    public function test_cannot_create_from_non_numeric(): void
    {
        $this->expectException(InvalidNIPException::class);
        NIP::fromString('12345678ab');
    }

    public function test_cannot_create_from_empty_string(): void
    {
        $this->expectException(InvalidNIPException::class);
        NIP::fromString('');
    }

    public function test_two_nips_with_same_value_are_equal(): void
    {
        $nip1 = NIP::fromString('123-456-78-90');
        $nip2 = NIP::fromString('1234567890');

        $this->assertTrue($nip1->equals($nip2));
    }

    public function test_formats_nip_with_dashes(): void
    {
        $nip = NIP::fromString('1234567890');

        $this->assertEquals('123-456-78-90', $nip->toFormattedString());
    }
}
