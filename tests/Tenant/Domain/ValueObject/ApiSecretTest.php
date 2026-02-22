<?php

declare(strict_types=1);

namespace App\Tests\Tenant\Domain\ValueObject;

use App\Domain\Tenant\ValueObject\ApiSecret;
use PHPUnit\Framework\TestCase;

final class ApiSecretTest extends TestCase
{
    public function test_generates_random_api_secret(): void
    {
        $apiSecret = ApiSecret::generate();

        $this->assertEquals(64, strlen($apiSecret->toString()));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $apiSecret->toString());
    }

    public function test_two_generated_secrets_are_different(): void
    {
        $secret1 = ApiSecret::generate();
        $secret2 = ApiSecret::generate();

        $this->assertNotEquals($secret1->toString(), $secret2->toString());
    }

    public function test_can_create_from_string(): void
    {
        $secretString = str_repeat('a1b2c3d4', 8);
        $apiSecret = ApiSecret::fromString($secretString);

        $this->assertEquals($secretString, $apiSecret->toString());
    }

    public function test_two_secrets_with_same_value_are_equal(): void
    {
        $secretString = str_repeat('a1b2c3d4', 8);
        $secret1 = ApiSecret::fromString($secretString);
        $secret2 = ApiSecret::fromString($secretString);

        $this->assertTrue($secret1->equals($secret2));
    }
}
