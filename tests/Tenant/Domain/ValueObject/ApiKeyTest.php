<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Domain\ValueObject;

use App\Domain\Tenant\ValueObject\ApiKey;
use PHPUnit\Framework\TestCase;

final class ApiKeyTest extends TestCase
{
    public function test_generates_random_api_key(): void
    {
        $apiKey = ApiKey::generate();

        $this->assertEquals(32, strlen($apiKey->toString()));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $apiKey->toString());
    }

    public function test_two_generated_keys_are_different(): void
    {
        $key1 = ApiKey::generate();
        $key2 = ApiKey::generate();

        $this->assertNotEquals($key1->toString(), $key2->toString());
    }

    public function test_can_create_from_string(): void
    {
        $keyString = 'a1b2c3d4e5f6789012345678901234ab';
        $apiKey = ApiKey::fromString($keyString);

        $this->assertEquals($keyString, $apiKey->toString());
    }

    public function test_two_keys_with_same_value_are_equal(): void
    {
        $keyString = 'a1b2c3d4e5f6789012345678901234ab';
        $key1 = ApiKey::fromString($keyString);
        $key2 = ApiKey::fromString($keyString);

        $this->assertTrue($key1->equals($key2));
    }
}
