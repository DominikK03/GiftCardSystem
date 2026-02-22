<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Infrastructure\Persistence\ReadModel;

use App\Application\GiftCard\Port\GiftCardReadModelRepositoryInterface;
use App\Application\GiftCard\Port\GiftCardReadModelWriterInterface;
use App\Application\GiftCard\ReadModel\GiftCardReadModel;
use App\Domain\GiftCard\Event\GiftCardActivated;
use App\Domain\GiftCard\Event\GiftCardBalanceAdjusted;
use App\Domain\GiftCard\Event\GiftCardBalanceDecreased;
use App\Domain\GiftCard\Event\GiftCardCancelled;
use App\Domain\GiftCard\Event\GiftCardCreated;
use App\Domain\GiftCard\Event\GiftCardDepleted;
use App\Domain\GiftCard\Event\GiftCardExpired;
use App\Domain\GiftCard\Event\GiftCardReactivated;
use App\Domain\GiftCard\Event\GiftCardRedeemed;
use App\Domain\GiftCard\Event\GiftCardSuspended;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Infrastructure\GiftCard\Persistence\ReadModel\GiftCardReadModelProjection;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GiftCardReadModelProjectionTest extends TestCase
{
    private const TENANT_ID = '550e8400-e29b-41d4-a716-446655440000';

    public function test_it_creates_read_model_on_created_event(): void
    {
        $queryRepo = $this->createStub(GiftCardReadModelRepositoryInterface::class);
        $writerRepo = $this->createMock(GiftCardReadModelWriterInterface::class);

        $writerRepo
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (GiftCardReadModel $readModel) {
                return $readModel->id === '11111111-1111-1111-1111-111111111111'
                    && $readModel->balanceAmount === 1000
                    && $readModel->balanceCurrency === 'PLN'
                    && $readModel->initialAmount === 1000
                    && $readModel->initialCurrency === 'PLN'
                    && $readModel->status === 'inactive'
                    && $readModel->createdAt->format('c') === '2025-01-01T10:00:00+00:00'
                    && $readModel->expiresAt?->format('c') === '2026-01-01T10:00:00+00:00';
            }));

        $projection = new GiftCardReadModelProjection($queryRepo, $writerRepo);

        $event = new GiftCardCreated(
            id: '11111111-1111-1111-1111-111111111111',
            tenantId: self::TENANT_ID,
            amount: 1000,
            currency: 'PLN',
            createdAt: '2025-01-01T10:00:00+00:00',
            expiresAt: '2026-01-01T10:00:00+00:00'
        );

        $projection->onGiftCardCreated($event);
    }

    public function test_it_activates_on_activate_event(): void
    {
        $id = GiftCardId::generate()->toString();
        $queryRepo = $this->createMock(GiftCardReadModelRepositoryInterface::class);
        $writerRepo = $this->createMock(GiftCardReadModelWriterInterface::class);

        $readModel = new GiftCardReadModel(
            id: $id,
            tenantId: self::TENANT_ID,
            balanceAmount: 500,
            balanceCurrency: 'PLN',
            initialAmount: 500,
            initialCurrency: 'PLN',
            status: 'inactive',
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            expiresAt: null
        );

        $queryRepo->expects($this->once())->method('findById')->willReturn($readModel);
        $writerRepo->expects($this->once())->method('save')->with($readModel);

        $projection = new GiftCardReadModelProjection($queryRepo, $writerRepo);

        $event = new GiftCardActivated(
            id: $id,
            activatedAt: '2025-01-02T10:00:00+00:00'
        );

        $projection->onGiftCardActivated($event);

        $this->assertSame('active', $readModel->status);
        $this->assertSame('2025-01-02T10:00:00+00:00', $readModel->activatedAt?->format('c'));
    }

    public function test_it_reduces_balance_on_redeem_event(): void
    {
        $id = GiftCardId::generate()->toString();
        $queryRepo = $this->createMock(GiftCardReadModelRepositoryInterface::class);
        $writerRepo = $this->createMock(GiftCardReadModelWriterInterface::class);

        $readModel = new GiftCardReadModel(
            id: $id,
            tenantId: self::TENANT_ID,
            balanceAmount: 1000,
            balanceCurrency: 'PLN',
            initialAmount: 1000,
            initialCurrency: 'PLN',
            status: 'active',
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            expiresAt: null
        );

        $queryRepo->expects($this->once())->method('findById')->willReturn($readModel);
        $writerRepo->expects($this->once())->method('save')->with($readModel);

        $projection = new GiftCardReadModelProjection($queryRepo, $writerRepo);

        $event = new GiftCardRedeemed(
            id: $id,
            amount: 200,
            currency: 'PLN',
            redeemedAt: '2025-01-02T10:00:00+00:00'
        );

        $projection->onGiftCardRedeemed($event);

        $this->assertSame(800, $readModel->balanceAmount);
    }

    public function test_it_marks_depleted_on_depleted_event(): void
    {
        $id = GiftCardId::generate()->toString();
        $queryRepo = $this->createMock(GiftCardReadModelRepositoryInterface::class);
        $writerRepo = $this->createMock(GiftCardReadModelWriterInterface::class);

        $readModel = new GiftCardReadModel(
            id: $id,
            tenantId: self::TENANT_ID,
            balanceAmount: 0,
            balanceCurrency: 'PLN',
            initialAmount: 1000,
            initialCurrency: 'PLN',
            status: 'active',
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            expiresAt: null
        );

        $queryRepo->expects($this->once())->method('findById')->willReturn($readModel);
        $writerRepo->expects($this->once())->method('save')->with($readModel);

        $projection = new GiftCardReadModelProjection($queryRepo, $writerRepo);

        $event = new GiftCardDepleted(
            id: $id,
            depletedAt: '2025-01-03T10:00:00+00:00'
        );

        $projection->onGiftCardDepleted($event);

        $this->assertSame('depleted', $readModel->status);
        $this->assertSame('2025-01-03T10:00:00+00:00', $readModel->depletedAt?->format('c'));
    }

    public function test_it_suspends_on_suspended_event(): void
    {
        $id = GiftCardId::generate()->toString();
        $queryRepo = $this->createMock(GiftCardReadModelRepositoryInterface::class);
        $writerRepo = $this->createMock(GiftCardReadModelWriterInterface::class);

        $readModel = new GiftCardReadModel(
            id: $id,
            tenantId: self::TENANT_ID,
            balanceAmount: 1000,
            balanceCurrency: 'PLN',
            initialAmount: 1000,
            initialCurrency: 'PLN',
            status: 'active',
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            expiresAt: null
        );

        $queryRepo->expects($this->once())->method('findById')->willReturn($readModel);
        $writerRepo->expects($this->once())->method('save')->with($readModel);

        $projection = new GiftCardReadModelProjection($queryRepo, $writerRepo);

        $event = new GiftCardSuspended(
            id: $id,
            reason: 'Suspended',
            suspendedAt: '2025-01-04T10:00:00+00:00',
            suspensionDurationSeconds: 3600
        );

        $projection->onGiftCardSuspended($event);

        $this->assertSame('suspended', $readModel->status);
        $this->assertSame('2025-01-04T10:00:00+00:00', $readModel->suspendedAt?->format('c'));
        $this->assertSame(3600, $readModel->suspensionDuration);
    }

    public function test_it_reactivates_on_reactivated_event(): void
    {
        $id = GiftCardId::generate()->toString();
        $queryRepo = $this->createMock(GiftCardReadModelRepositoryInterface::class);
        $writerRepo = $this->createMock(GiftCardReadModelWriterInterface::class);

        $readModel = new GiftCardReadModel(
            id: $id,
            tenantId: self::TENANT_ID,
            balanceAmount: 1000,
            balanceCurrency: 'PLN',
            initialAmount: 1000,
            initialCurrency: 'PLN',
            status: 'suspended',
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            expiresAt: new DateTimeImmutable('2026-01-01T10:00:00+00:00')
        );
        $readModel->suspendedAt = new DateTimeImmutable('2025-01-04T10:00:00+00:00');

        $queryRepo->expects($this->once())->method('findById')->willReturn($readModel);
        $writerRepo->expects($this->once())->method('save')->with($readModel);

        $projection = new GiftCardReadModelProjection($queryRepo, $writerRepo);

        $event = new GiftCardReactivated(
            id: $id,
            reason: null,
            reactivatedAt: '2025-01-05T10:00:00+00:00',
            newExpiresAt: '2026-02-01T10:00:00+00:00'
        );

        $projection->onGiftCardReactivated($event);

        $this->assertSame('active', $readModel->status);
        $this->assertNull($readModel->suspendedAt);
        $this->assertSame('2026-02-01T10:00:00+00:00', $readModel->expiresAt?->format('c'));
    }

    public function test_it_cancels_on_cancelled_event(): void
    {
        $id = GiftCardId::generate()->toString();
        $queryRepo = $this->createMock(GiftCardReadModelRepositoryInterface::class);
        $writerRepo = $this->createMock(GiftCardReadModelWriterInterface::class);

        $readModel = new GiftCardReadModel(
            id: $id,
            tenantId: self::TENANT_ID,
            balanceAmount: 1000,
            balanceCurrency: 'PLN',
            initialAmount: 1000,
            initialCurrency: 'PLN',
            status: 'active',
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            expiresAt: null
        );

        $queryRepo->expects($this->once())->method('findById')->willReturn($readModel);
        $writerRepo->expects($this->once())->method('save')->with($readModel);

        $projection = new GiftCardReadModelProjection($queryRepo, $writerRepo);

        $event = new GiftCardCancelled(
            id: $id,
            reason: 'Customer requested',
            cancelledAt: '2025-01-06T10:00:00+00:00'
        );

        $projection->onGiftCardCancelled($event);

        $this->assertSame('cancelled', $readModel->status);
        $this->assertSame('2025-01-06T10:00:00+00:00', $readModel->cancelledAt?->format('c'));
    }

    public function test_it_expires_on_expired_event(): void
    {
        $id = GiftCardId::generate()->toString();
        $queryRepo = $this->createMock(GiftCardReadModelRepositoryInterface::class);
        $writerRepo = $this->createMock(GiftCardReadModelWriterInterface::class);

        $readModel = new GiftCardReadModel(
            id: $id,
            tenantId: self::TENANT_ID,
            balanceAmount: 1000,
            balanceCurrency: 'PLN',
            initialAmount: 1000,
            initialCurrency: 'PLN',
            status: 'active',
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            expiresAt: new DateTimeImmutable('2026-01-01T10:00:00+00:00')
        );

        $queryRepo->expects($this->once())->method('findById')->willReturn($readModel);
        $writerRepo->expects($this->once())->method('save')->with($readModel);

        $projection = new GiftCardReadModelProjection($queryRepo, $writerRepo);

        $event = new GiftCardExpired(
            id: $id,
            expiredAt: '2026-01-02T10:00:00+00:00'
        );

        $projection->onGiftCardExpired($event);

        $this->assertSame('expired', $readModel->status);
        $this->assertSame('2026-01-02T10:00:00+00:00', $readModel->expiredAt?->format('c'));
    }

    public function test_it_adjusts_balance_on_adjusted_event(): void
    {
        $id = GiftCardId::generate()->toString();
        $queryRepo = $this->createMock(GiftCardReadModelRepositoryInterface::class);
        $writerRepo = $this->createMock(GiftCardReadModelWriterInterface::class);

        $readModel = new GiftCardReadModel(
            id: $id,
            tenantId: self::TENANT_ID,
            balanceAmount: 1000,
            balanceCurrency: 'PLN',
            initialAmount: 1000,
            initialCurrency: 'PLN',
            status: 'active',
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            expiresAt: null
        );

        $queryRepo->expects($this->once())->method('findById')->willReturn($readModel);
        $writerRepo->expects($this->once())->method('save')->with($readModel);

        $projection = new GiftCardReadModelProjection($queryRepo, $writerRepo);

        $event = new GiftCardBalanceAdjusted(
            id: $id,
            adjustmentAmount: 300,
            adjustmentCurrency: 'PLN',
            reason: 'Bonus',
            adjustedAt: '2025-01-07T10:00:00+00:00'
        );

        $projection->onGiftCardBalanceAdjusted($event);

        $this->assertSame(1300, $readModel->balanceAmount);
    }

    public function test_it_decreases_balance_on_decreased_event(): void
    {
        $id = GiftCardId::generate()->toString();
        $queryRepo = $this->createMock(GiftCardReadModelRepositoryInterface::class);
        $writerRepo = $this->createMock(GiftCardReadModelWriterInterface::class);

        $readModel = new GiftCardReadModel(
            id: $id,
            tenantId: self::TENANT_ID,
            balanceAmount: 1000,
            balanceCurrency: 'PLN',
            initialAmount: 1000,
            initialCurrency: 'PLN',
            status: 'active',
            createdAt: new DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            expiresAt: null
        );

        $queryRepo->expects($this->once())->method('findById')->willReturn($readModel);
        $writerRepo->expects($this->once())->method('save')->with($readModel);

        $projection = new GiftCardReadModelProjection($queryRepo, $writerRepo);

        $event = new GiftCardBalanceDecreased(
            id: $id,
            amount: 250,
            currency: 'PLN',
            reason: 'Correction',
            decreasedAt: '2025-01-08T10:00:00+00:00'
        );

        $projection->onGiftCardBalanceDecreased($event);

        $this->assertSame(750, $readModel->balanceAmount);
    }
}
