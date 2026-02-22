<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Application\Provider;

use App\Application\GiftCard\Provider\GiftCardProvider;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\Exception\GiftCardNotFoundException;
use App\Domain\GiftCard\Port\GiftCardRepository;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
use App\Domain\Tenant\Exception\TenantMismatchException;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Infrastructure\Tenant\TenantContext;
use PHPUnit\Framework\TestCase;

final class GiftCardProviderTest extends TestCase
{
    private const TENANT_A_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const TENANT_B_ID = '660e8400-e29b-41d4-a716-446655440000';

    public function test_it_loads_gift_card_when_tenant_matches(): void
    {
        $repository = $this->createMock(GiftCardRepository::class);
        $tenantContext = $this->createMock(TenantContext::class);

        $giftCardId = GiftCardId::generate();
        $giftCard = GiftCard::create(
            $giftCardId,
            self::TENANT_A_ID,
            Money::fromPrimitives(1000, 'PLN'),
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            new \DateTimeImmutable('2026-01-01T10:00:00+00:00')
        );

        $repository->expects($this->once())
            ->method('load')
            ->with($giftCardId)
            ->willReturn($giftCard);

        $tenantContext->expects($this->once())
            ->method('getTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_A_ID));

        $provider = new GiftCardProvider($repository, $tenantContext);

        $result = $provider->loadFromId($giftCardId);

        $this->assertSame($giftCard, $result);
    }

    public function test_it_throws_exception_when_tenant_mismatch(): void
    {
        $repository = $this->createMock(GiftCardRepository::class);
        $tenantContext = $this->createMock(TenantContext::class);

        $giftCardId = GiftCardId::generate();
        $giftCard = GiftCard::create(
            $giftCardId,
            self::TENANT_A_ID,
            Money::fromPrimitives(1000, 'PLN'),
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            new \DateTimeImmutable('2026-01-01T10:00:00+00:00')
        );

        $repository->expects($this->once())
            ->method('load')
            ->with($giftCardId)
            ->willReturn($giftCard);

        $tenantContext->expects($this->once())
            ->method('getTenantId')
            ->willReturn(TenantId::fromString(self::TENANT_B_ID));

        $provider = new GiftCardProvider($repository, $tenantContext);

        $this->expectException(TenantMismatchException::class);
        $this->expectExceptionMessage('Tenant mismatch. Current tenant "' . self::TENANT_B_ID . '" cannot access resources of tenant "' . self::TENANT_A_ID . '"');

        $provider->loadFromId($giftCardId);
    }

    public function test_it_throws_exception_when_gift_card_not_found(): void
    {
        $repository = $this->createMock(GiftCardRepository::class);
        $tenantContext = $this->createMock(TenantContext::class);

        $giftCardId = GiftCardId::generate();

        $repository->expects($this->once())
            ->method('load')
            ->with($giftCardId)
            ->willReturn(null);

        $tenantContext->expects($this->never())
            ->method('getTenantId');

        $provider = new GiftCardProvider($repository, $tenantContext);

        $this->expectException(GiftCardNotFoundException::class);

        $provider->loadFromId($giftCardId);
    }
}
