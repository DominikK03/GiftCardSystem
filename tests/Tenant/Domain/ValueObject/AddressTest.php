<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Domain\ValueObject;

use App\Domain\Tenant\Exception\InvalidAddressException;
use App\Domain\Tenant\ValueObject\Address;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    public function test_can_create_valid_address(): void
    {
        $address = Address::create(
            street: 'ul. Testowa 123',
            city: 'Warszawa',
            postalCode: '00-001',
            country: 'Polska'
        );

        $this->assertEquals('ul. Testowa 123', $address->getStreet());
        $this->assertEquals('Warszawa', $address->getCity());
        $this->assertEquals('00-001', $address->getPostalCode());
        $this->assertEquals('Polska', $address->getCountry());
    }

    public function test_cannot_create_with_empty_street(): void
    {
        $this->expectException(InvalidAddressException::class);

        Address::create(
            street: '',
            city: 'Warszawa',
            postalCode: '00-001',
            country: 'Polska'
        );
    }

    public function test_cannot_create_with_empty_city(): void
    {
        $this->expectException(InvalidAddressException::class);

        Address::create(
            street: 'ul. Testowa 123',
            city: '',
            postalCode: '00-001',
            country: 'Polska'
        );
    }

    public function test_cannot_create_with_empty_postal_code(): void
    {
        $this->expectException(InvalidAddressException::class);

        Address::create(
            street: 'ul. Testowa 123',
            city: 'Warszawa',
            postalCode: '',
            country: 'Polska'
        );
    }

    public function test_cannot_create_with_empty_country(): void
    {
        $this->expectException(InvalidAddressException::class);

        Address::create(
            street: 'ul. Testowa 123',
            city: 'Warszawa',
            postalCode: '00-001',
            country: ''
        );
    }

    public function test_two_addresses_with_same_values_are_equal(): void
    {
        $address1 = Address::create('ul. Testowa 123', 'Warszawa', '00-001', 'Polska');
        $address2 = Address::create('ul. Testowa 123', 'Warszawa', '00-001', 'Polska');

        $this->assertTrue($address1->equals($address2));
    }

    public function test_can_format_as_string(): void
    {
        $address = Address::create('ul. Testowa 123', 'Warszawa', '00-001', 'Polska');

        $this->assertEquals(
            'ul. Testowa 123, 00-001 Warszawa, Polska',
            $address->toString()
        );
    }
}
