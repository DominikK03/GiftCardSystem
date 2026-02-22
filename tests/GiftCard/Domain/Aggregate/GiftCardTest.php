<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Domain\Aggregate;

use App\Domain\GiftCard\Aggregate\GiftCard;
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
use App\Domain\GiftCard\Exception\GiftCardAlreadyExpiredException;
use App\Domain\GiftCard\Exception\GiftCardNotActiveException;
use App\Domain\GiftCard\Exception\InsufficientBalanceException;
use App\Domain\GiftCard\Exception\InvalidExpirationDateException;
use App\Domain\GiftCard\Exception\InvalidSuspensionStateException;
use App\Domain\GiftCard\Exception\NoExpirationDateException;
use App\Domain\GiftCard\ValueObject\CardNumber;
use App\Domain\GiftCard\ValueObject\CardPin;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
use Broadway\EventSourcing\Testing\AggregateRootScenarioTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;

class GiftCardTest extends AggregateRootScenarioTestCase
{
    private const STANDARD_CURRENCY = 'PLN';
    private const TENANT_ID = '550e8400-e29b-41d4-a716-446655440000';

    protected function getAggregateRootClass(): string
    {
        return GiftCard::class;
    }

    private function createGiftCardId(): GiftCardId
    {
        return GiftCardId::generate();
    }

    private function createStandardAmount(): Money
    {
        return new Money(1000, self::STANDARD_CURRENCY);
    }

    private function createSmallAmount(): Money
    {
        return new Money(300, self::STANDARD_CURRENCY);
    }

    private function createStandardDate(): DateTimeImmutable
    {
        return new DateTimeImmutable('2025-01-01 12:00:00');
    }

    private function createExpirationDate(): DateTimeImmutable
    {
        return $this->createStandardDate()->modify('+1 year');
    }

    #[Test]
    public function it_creates_gift_card_with_initial_amount(): void
    {
        $id = $this->createGiftCardId();
        $amount = $this->createStandardAmount();
        $expiresAt = $this->createExpirationDate();
        $createdAt = $this->createStandardDate();
        $giftCard = GiftCard::create($id, self::TENANT_ID, $amount, $createdAt, $expiresAt);
        $events = iterator_to_array($giftCard->getUncommittedEvents());

        self::assertCount(1, $events);
        $event = $events[0]->getPayload();
        self::assertInstanceOf(GiftCardCreated::class, $event);
        self::assertSame($id->toString(), $event->id);
        self::assertSame(self::TENANT_ID, $event->tenantId);
        self::assertSame($amount->getAmount(), $event->amount);
        self::assertSame($amount->getCurrency(), $event->currency);
        self::assertSame($createdAt->format('Y-m-d\TH:i:s.uP'), $event->createdAt);
        self::assertSame($expiresAt->format('Y-m-d\TH:i:s.uP'), $event->expiresAt);
        self::assertNotNull($event->cardNumber);
        self::assertNotNull($event->pin);
        self::assertNotNull($giftCard->getCardNumber());
        self::assertNotNull($giftCard->getPin());
        self::assertSame($event->cardNumber, $giftCard->getCardNumber()?->toString());
        self::assertSame($event->pin, $giftCard->getPin()?->toString());
        CardNumber::fromString($event->cardNumber);
        CardPin::fromString($event->pin);
    }

    #[Test]
    public function it_redeems_gift_card_and_decreases_balance(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $redeemAmount = $this->createSmallAmount();
        $createdAt = $this->createStandardDate();
        $expiresAt = $this->createExpirationDate();

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardActivated(
                    $id->toString(),
                    $createdAt->format('Y-m-d\TH:i:s.uP')
                )
            ])
            ->when(function (GiftCard $giftCard) use ($redeemAmount, $createdAt) {
                $giftCard->redeem($redeemAmount, $createdAt);
            })
            ->then([
                new GiftCardRedeemed(
                    $id->toString(),
                    $redeemAmount->getAmount(),
                    $redeemAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP')
                )
            ]);
    }

    #[Test]
    public function it_activates_inactive_gift_card(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $createdAt = $this->createStandardDate();
        $activatedAt = $this->createStandardDate();
        $expiresAt = $this->createExpirationDate();

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                )
            ])
            ->when(function (GiftCard $giftCard) use ($activatedAt) {
                $giftCard->activate($activatedAt);
            })
            ->then([
                new GiftCardActivated(
                    $id->toString(),
                    $activatedAt->format('Y-m-d\TH:i:s.uP')
                )
            ]);
    }

    #[Test]
    public function it_suspends_active_gift_card(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $createdAt = $this->createStandardDate();
        $activatedAt = $this->createStandardDate();
        $expiresAt = $this->createExpirationDate();
        $reason = "Test";
        $suspendedAt = new DateTimeImmutable();
        $durationSeconds = 86400;

        $this->scenario
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardActivated(
                    $id->toString(),
                    $activatedAt->format('Y-m-d\TH:i:s.uP')
                )
            ])
            ->when(function (GiftCard $giftCard) use ($reason, $durationSeconds, $suspendedAt) {
                $giftCard->suspend($reason, $durationSeconds, $suspendedAt);
            })
            ->then([
                new GiftCardSuspended(
                    $id->toString(),
                    $reason,
                    $suspendedAt->format('Y-m-d\TH:i:s.uP'),
                    $durationSeconds
                )
            ]);
    }

    #[Test]
    public function it_reactivates_suspended_gift_card(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $createdAt = $this->createStandardDate();
        $expiresAt = $this->createExpirationDate();
        $suspendedAt = $createdAt->modify('+10 days');
        $reactivatedAt = $suspendedAt->modify('+5 days');
        $suspensionDurationSeconds = 5 * 86400;

        $newExpiresAt = $expiresAt->modify("+{$suspensionDurationSeconds} seconds");

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardActivated(
                    $id->toString(),
                    $createdAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardSuspended(
                    $id->toString(),
                    'Test suspension',
                    $suspendedAt->format('Y-m-d\TH:i:s.uP'),
                    $suspensionDurationSeconds
                )
            ])
            ->when(function (GiftCard $giftCard) use ($reactivatedAt) {
                $giftCard->reactivate('Test reactivation', $reactivatedAt);
            })
            ->then([
                new GiftCardReactivated(
                    $id->toString(),
                    'Test reactivation',
                    $reactivatedAt->format('Y-m-d\TH:i:s.uP'),
                    $newExpiresAt->format('Y-m-d\TH:i:s.uP')
                )
            ]);
    }

    #[Test]
    public function it_cancels_active_gift_card(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $createdAt = $this->createStandardDate();
        $expiresAt = $this->createExpirationDate();
        $cancelledAt = $createdAt->modify('+10 days');

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardActivated(
                    $id->toString(),
                    $createdAt->format('Y-m-d\TH:i:s.uP')
                )
            ])
            ->when(function (GiftCard $giftCard) use ($cancelledAt) {
                $giftCard->cancel('Customer requested', $cancelledAt);
            })
            ->then([
                new GiftCardCancelled(
                    $id->toString(),
                    'Customer requested',
                    $cancelledAt->format('Y-m-d\TH:i:s.uP')
                )
            ]);
    }

    #[Test]
    public function it_expires_active_gift_card(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $createdAt = $this->createStandardDate();
        $expiresAt = $createdAt->modify('+30 days');
        $expiredAt = $createdAt->modify('+31 days');

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardActivated(
                    $id->toString(),
                    $createdAt->format('Y-m-d\TH:i:s.uP')
                )
            ])
            ->when(function (GiftCard $giftCard) use ($expiredAt) {
                $giftCard->expire($expiredAt);
            })
            ->then([
                new GiftCardExpired(
                    $id->toString(),
                    $expiredAt->format('Y-m-d\TH:i:s.uP')
                )
            ]);
    }

    #[Test]
    public function it_adjusts_balance_upward(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $createdAt = $this->createStandardDate();
        $expiresAt = $this->createExpirationDate();
        $adjustment = new Money(500, self::STANDARD_CURRENCY);
        $adjustedAt = $createdAt->modify('+1 day');

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardActivated(
                    $id->toString(),
                    $createdAt->format('Y-m-d\TH:i:s.uP')
                )
            ])
            ->when(function (GiftCard $giftCard) use ($adjustment, $adjustedAt) {
                $giftCard->adjustBalance($adjustment, 'Bonus credit', $adjustedAt);
            })
            ->then([
                new GiftCardBalanceAdjusted(
                    $id->toString(),
                    $adjustment->getAmount(),
                    $adjustment->getCurrency(),
                    'Bonus credit',
                    $adjustedAt->format('Y-m-d\TH:i:s.uP')
                )
            ]);
    }

    #[Test]
    public function it_depletes_gift_card_when_redeeming_full_amount(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $createdAt = $this->createStandardDate();
        $expiresAt = $this->createExpirationDate();
        $redeemedAt = $createdAt->modify('+1 day');

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardActivated(
                    $id->toString(),
                    $createdAt->format('Y-m-d\TH:i:s.uP')
                )
            ])
            ->when(function (GiftCard $giftCard) use ($initialAmount, $redeemedAt) {
                $giftCard->redeem($initialAmount, $redeemedAt);
            })
            ->then([
                new GiftCardRedeemed(
                    $id->toString(),
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $redeemedAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardDepleted(
                    $id->toString(),
                    $redeemedAt->format('Y-m-d\TH:i:s.uP')
                )
            ]);
    }

    #[Test]
    public function it_throws_exception_when_insufficient_balance(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = new Money(100, self::STANDARD_CURRENCY);
        $redeemAmount = new Money(200, self::STANDARD_CURRENCY);
        $createdAt = $this->createStandardDate();
        $expiresAt = $this->createExpirationDate();
        $redeemedAt = $createdAt->modify('+1 day');

        $this->expectException(InsufficientBalanceException::class);

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardActivated(
                    $id->toString(),
                    $createdAt->format('Y-m-d\TH:i:s.uP')
                )
            ])
            ->when(function (GiftCard $giftCard) use ($redeemAmount, $redeemedAt) {
                $giftCard->redeem($redeemAmount, $redeemedAt);
            });
    }

    #[Test]
    public function it_throws_exception_when_redeeming_inactive_card(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $redeemAmount = $this->createSmallAmount();
        $createdAt = $this->createStandardDate();
        $expiresAt = $this->createExpirationDate();
        $redeemedAt = $createdAt->modify('+1 day');

        $this->expectException(GiftCardNotActiveException::class);

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                )
            ])
            ->when(function (GiftCard $giftCard) use ($redeemAmount, $redeemedAt) {
                $giftCard->redeem($redeemAmount, $redeemedAt);
            });
    }

    #[Test]
    public function it_throws_exception_when_creating_with_past_expiration_date(): void
    {
        $id = $this->createGiftCardId();
        $amount = $this->createStandardAmount();
        $createdAt = $this->createStandardDate();
        $expiresAt = $createdAt->modify('-1 day');

        $this->expectException(InvalidExpirationDateException::class);

        GiftCard::create($id, self::TENANT_ID, $amount, $createdAt, $expiresAt);
    }

    #[Test]
    public function it_throws_exception_when_activating_expired_gift_card(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $createdAt = $this->createStandardDate();
        $expiresAt = $createdAt->modify('+1 day');
        $activatedAt = $expiresAt->modify('+1 day');

        $this->expectException(GiftCardAlreadyExpiredException::class);

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                )
            ])
            ->when(function (GiftCard $giftCard) use ($activatedAt) {
                $giftCard->activate($activatedAt);
            });
    }

    #[Test]
    public function it_throws_exception_when_redeeming_expired_gift_card(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $createdAt = $this->createStandardDate();
        $activatedAt = $createdAt->modify('+1 day');
        $expiresAt = $createdAt->modify('+2 days');
        $redeemedAt = $createdAt->modify('+3 days');

        $this->expectException(GiftCardAlreadyExpiredException::class);

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardActivated(
                    $id->toString(),
                    $activatedAt->format('Y-m-d\TH:i:s.uP')
                )
            ])
            ->when(function (GiftCard $giftCard) use ($redeemedAt) {
                $giftCard->redeem($this->createSmallAmount(), $redeemedAt);
            });
    }

    #[Test]
    public function it_throws_exception_when_expiring_without_expiration_date(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $createdAt = $this->createStandardDate();
        $expiresAt = $this->createExpirationDate();
        $suspendedAt = $createdAt->modify('+1 day');
        $reactivatedAt = $createdAt->modify('+2 days');
        $expiredAt = $createdAt->modify('+3 days');

        $this->expectException(NoExpirationDateException::class);

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardActivated(
                    $id->toString(),
                    $createdAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardSuspended(
                    $id->toString(),
                    'Test suspension',
                    $suspendedAt->format('Y-m-d\TH:i:s.uP'),
                    3600
                ),
                new GiftCardReactivated(
                    $id->toString(),
                    null,
                    $reactivatedAt->format('Y-m-d\TH:i:s.uP'),
                    null
                )
            ])
            ->when(function (GiftCard $giftCard) use ($expiredAt) {
                $giftCard->expire($expiredAt);
            });
    }

    #[Test]
    public function it_throws_exception_when_reactivating_without_suspension_duration(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $createdAt = $this->createStandardDate();
        $expiresAt = $this->createExpirationDate();
        $suspendedAt = $createdAt->modify('+1 day');
        $reactivatedAt = $createdAt->modify('+2 days');

        $this->expectException(InvalidSuspensionStateException::class);

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardActivated(
                    $id->toString(),
                    $createdAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardSuspended(
                    $id->toString(),
                    'Test suspension',
                    $suspendedAt->format('Y-m-d\TH:i:s.uP'),
                    3600
                )
            ])
            ->when(function (GiftCard $giftCard) use ($reactivatedAt) {
                $reflection = new \ReflectionClass($giftCard);
                $property = $reflection->getProperty('suspensionDurationSeconds');
                $property->setAccessible(true);
                $property->setValue($giftCard, null);

                $giftCard->reactivate('Test reactivation', $reactivatedAt);
            });
    }

    #[Test]
    public function it_decreases_balance(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $createdAt = $this->createStandardDate();
        $expiresAt = $this->createExpirationDate();
        $decreaseAmount = new Money(200, self::STANDARD_CURRENCY);
        $decreasedAt = $createdAt->modify('+1 day');

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardActivated(
                    $id->toString(),
                    $createdAt->format('Y-m-d\TH:i:s.uP')
                )
            ])
            ->when(function (GiftCard $giftCard) use ($decreaseAmount, $decreasedAt) {
                $giftCard->decreaseBalance($decreaseAmount, 'Adjustment', $decreasedAt);
            })
            ->then([
                new GiftCardBalanceDecreased(
                    $id->toString(),
                    $decreaseAmount->getAmount(),
                    $decreaseAmount->getCurrency(),
                    'Adjustment',
                    $decreasedAt->format('Y-m-d\TH:i:s.uP')
                )
            ]);
    }

    #[Test]
    public function it_depletes_gift_card_when_decreasing_full_amount(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = $this->createStandardAmount();
        $createdAt = $this->createStandardDate();
        $expiresAt = $this->createExpirationDate();
        $decreasedAt = $createdAt->modify('+1 day');

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardActivated(
                    $id->toString(),
                    $createdAt->format('Y-m-d\TH:i:s.uP')
                )
            ])
            ->when(function (GiftCard $giftCard) use ($initialAmount, $decreasedAt) {
                $giftCard->decreaseBalance($initialAmount, 'Adjustment', $decreasedAt);
            })
            ->then([
                new GiftCardBalanceDecreased(
                    $id->toString(),
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    'Adjustment',
                    $decreasedAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardDepleted(
                    $id->toString(),
                    $decreasedAt->format('Y-m-d\TH:i:s.uP')
                )
            ]);
    }

    #[Test]
    public function it_throws_exception_when_decreasing_balance_below_zero(): void
    {
        $id = $this->createGiftCardId();
        $initialAmount = new Money(100, self::STANDARD_CURRENCY);
        $decreaseAmount = new Money(200, self::STANDARD_CURRENCY);
        $createdAt = $this->createStandardDate();
        $expiresAt = $this->createExpirationDate();
        $decreasedAt = $createdAt->modify('+1 day');

        $this->expectException(InsufficientBalanceException::class);

        $this->scenario
            ->withAggregateId($id->toString())
            ->given([
                new GiftCardCreated(
                    $id->toString(),
                    self::TENANT_ID,
                    $initialAmount->getAmount(),
                    $initialAmount->getCurrency(),
                    $createdAt->format('Y-m-d\TH:i:s.uP'),
                    $expiresAt->format('Y-m-d\TH:i:s.uP')
                ),
                new GiftCardActivated(
                    $id->toString(),
                    $createdAt->format('Y-m-d\TH:i:s.uP')
                )
            ])
            ->when(function (GiftCard $giftCard) use ($decreaseAmount, $decreasedAt) {
                $giftCard->decreaseBalance($decreaseAmount, 'Adjustment', $decreasedAt);
            });
    }
}
