<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Application\Handler;

use App\Application\GiftCard\Handler\GetGiftCard;
use App\Application\GiftCard\Port\GiftCardReadModelRepositoryInterface;
use App\Application\GiftCard\Query\GetGiftCardQuery;
use App\Application\GiftCard\ReadModel\GiftCardReadModel;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GetGiftCardTest extends TestCase
{
    public function test_it_returns_null_when_missing(): void
    {
        $repository = $this->createMock(GiftCardReadModelRepositoryInterface::class);
        $repository->expects($this->once())->method('findById')->willReturn(null);

        $handler = new GetGiftCard($repository);

        $result = $handler->__invoke(new GetGiftCardQuery('missing-id'));

        $this->assertNull($result);
    }

    public function test_it_returns_view_when_found(): void
    {
        $repository = $this->createMock(GiftCardReadModelRepositoryInterface::class);

        $readModel = new GiftCardReadModel(
            id: 'id-1',
            tenantId: '550e8400-e29b-41d4-a716-446655440000',
            balanceAmount: 1000,
            balanceCurrency: 'PLN',
            initialAmount: 1000,
            initialCurrency: 'PLN',
            status: 'ACTIVE',
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            expiresAt: new DateTimeImmutable('2026-01-01T10:00:00+00:00')
        );
        $readModel->activatedAt = new DateTimeImmutable('2025-01-02T10:00:00+00:00');

        $repository->expects($this->once())->method('findById')->willReturn($readModel);

        $handler = new GetGiftCard($repository);

        $result = $handler->__invoke(new GetGiftCardQuery('id-1'));

        $this->assertNotNull($result);
        $this->assertSame('id-1', $result->id);
        $this->assertSame('ACTIVE', $result->status);
    }
}
