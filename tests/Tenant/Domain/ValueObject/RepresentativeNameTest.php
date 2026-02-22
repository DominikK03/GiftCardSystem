<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Domain\ValueObject;

use App\Domain\Tenant\Exception\InvalidRepresentativeNameException;
use App\Domain\Tenant\ValueObject\RepresentativeName;
use PHPUnit\Framework\TestCase;

final class RepresentativeNameTest extends TestCase
{
    public function test_can_create_valid_representative_name(): void
    {
        $name = RepresentativeName::create('Jan', 'Kowalski');

        $this->assertEquals('Jan', $name->getFirstName());
        $this->assertEquals('Kowalski', $name->getLastName());
    }

    public function test_cannot_create_with_empty_first_name(): void
    {
        $this->expectException(InvalidRepresentativeNameException::class);
        RepresentativeName::create('', 'Kowalski');
    }

    public function test_cannot_create_with_empty_last_name(): void
    {
        $this->expectException(InvalidRepresentativeNameException::class);
        RepresentativeName::create('Jan', '');
    }

    public function test_trims_whitespace(): void
    {
        $name = RepresentativeName::create('  Jan  ', '  Kowalski  ');

        $this->assertEquals('Jan', $name->getFirstName());
        $this->assertEquals('Kowalski', $name->getLastName());
    }

    public function test_two_names_with_same_values_are_equal(): void
    {
        $name1 = RepresentativeName::create('Jan', 'Kowalski');
        $name2 = RepresentativeName::create('Jan', 'Kowalski');

        $this->assertTrue($name1->equals($name2));
    }

    public function test_can_format_as_full_name(): void
    {
        $name = RepresentativeName::create('Jan', 'Kowalski');

        $this->assertEquals('Jan Kowalski', $name->getFullName());
    }
}
