<?php

declare(strict_types=1);

namespace App\Domain\GiftCard\Aggregate;

use App\Domain\GiftCard\Enum\GiftCardStatus;
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
use App\Domain\GiftCard\Exception\GiftCardNotExpiredException;
use App\Domain\GiftCard\Exception\InsufficientBalanceException;
use App\Domain\GiftCard\Exception\InvalidExpirationDateException;
use App\Domain\GiftCard\Exception\InvalidSuspensionStateException;
use App\Domain\GiftCard\Exception\NoExpirationDateException;
use App\Domain\GiftCard\Exception\WrongGiftCardStatusException;
use App\Domain\GiftCard\ValueObject\CardNumber;
use App\Domain\GiftCard\ValueObject\CardPin;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
use Broadway\EventSourcing\EventSourcedAggregateRoot;
use DateInterval;
use DateTimeImmutable;

class GiftCard extends EventSourcedAggregateRoot
{
    private GiftCardId $id;
    private string $tenantId;
    private Money $balance;
    private Money $initialAmount;
    private GiftCardStatus $status;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $expiresAt;
    private ?DateTimeImmutable $suspendedAt = null;
    private ?DateTimeImmutable $cancelledAt = null;
    private ?DateTimeImmutable $activatedAt = null;
    private ?DateTimeImmutable $depletedAt = null;
    private ?DateTimeImmutable $expiredAt = null;
    private ?int $suspensionDurationSeconds = null;
    private ?CardNumber $cardNumber = null;
    private ?CardPin $pin = null;


    public function getAggregateRootId(): string
    {
        return $this->id->__toString();
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public static function create(
        GiftCardId $id,
        string $tenantId,
        Money $amount,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $expiresAt = null

    ): self
    {
        $finalExpiresAt = $expiresAt ?? new DateTimeImmutable('+1 year');
        $finalCreatedAt = $createdAt ?? new DateTimeImmutable();

        if ($finalExpiresAt <= $finalCreatedAt){
            throw InvalidExpirationDateException::create();
        }

        $cardNumber = CardNumber::generate();
        $cardPin = CardPin::generate();

        $giftCard = new self();
        $giftCard->apply(new GiftCardCreated(
            $id->toString(),
            $tenantId,
            $amount->getAmount(),
            $amount->getCurrency(),
            $finalCreatedAt->format('Y-m-d\TH:i:s.uP'),
            $finalExpiresAt->format('Y-m-d\TH:i:s.uP'),
            $cardNumber->toString(),
            $cardPin->toString()
        ));
        return $giftCard;
    }

    protected function applyGiftCardCreated(GiftCardCreated $event): void
    {
        $this->id = GiftCardId::fromString($event->id);
        $this->tenantId = $event->tenantId;
        $amount = Money::fromPrimitives($event->amount, $event->currency);
        $this->balance = $amount;
        $this->initialAmount = $amount;
        $this->status = GiftCardStatus::INACTIVE;
        $this->createdAt = new DateTimeImmutable($event->createdAt);
        $this->expiresAt = new DateTimeImmutable($event->expiresAt);

        if ($event->cardNumber !== null) {
            $this->cardNumber = CardNumber::fromString($event->cardNumber);
        }
        if ($event->pin !== null) {
            $this->pin = CardPin::fromString($event->pin);
        }
    }

    public function getCardNumber(): ?CardNumber
    {
        return $this->cardNumber;
    }

    public function getPin(): ?CardPin
    {
        return $this->pin;
    }

    public function redeem(
        Money $amount,
        ?DateTimeImmutable $redeemedAt = null
    ): void
    {
        $finalRedeemedAt = $redeemedAt ?? new DateTimeImmutable();

        if ($this->status !== GiftCardStatus::ACTIVE){
            throw GiftCardNotActiveException::create($this->status);
        }

        if ($this->expiresAt !== null && $this->expiresAt <= $finalRedeemedAt) {
            throw GiftCardAlreadyExpiredException::cannotRedeem($this->expiresAt);
        }

        if (!$this->balance->isGreaterThanOrEqual($amount)){
            throw InsufficientBalanceException::notEnoughFunds($this->balance, $amount);
        }

        $this->apply(new GiftCardRedeemed(
            $this->id->toString(),
            $amount->getAmount(),
            $amount->getCurrency(),
            $finalRedeemedAt->format('Y-m-d\TH:i:s.uP')
        ));
        if ($this->balance->getAmount() === 0){
            $this->apply(new GiftCardDepleted(
                $this->id->toString(),
                $finalRedeemedAt->format('Y-m-d\TH:i:s.uP')
            ));
        }
    }

    public function applyGiftCardRedeemed(GiftCardRedeemed $event): void
    {
        $redeemAmount = Money::fromPrimitives($event->amount, $event->currency);
        $this->balance = $this->balance->subtract($redeemAmount);
    }

    protected function applyGiftCardDepleted(GiftCardDepleted $event): void
    {
        $this->status = GiftCardStatus::DEPLETED;
        $this->depletedAt = new DateTimeImmutable($event->depletedAt);
    }

    public function activate(?DateTimeImmutable $activatedAt = null): void
    {
        $finalActivatedAt = $activatedAt ?? new DateTimeImmutable();

        if ($this->status !== GiftCardStatus::INACTIVE){
            throw WrongGiftCardStatusException::create(GiftCardStatus::INACTIVE, $this->status);
        }

        if ($this->expiresAt !== null && $this->expiresAt <= $finalActivatedAt) {
            throw GiftCardAlreadyExpiredException::cannotActivate($this->expiresAt);
        }

        $this->apply(new GiftCardActivated(
            $this->id->toString(),
            $finalActivatedAt->format('Y-m-d\TH:i:s.uP')
        ));
    }

    protected function applyGiftCardActivated(GiftCardActivated $event): void
    {
        $this->status = GiftCardStatus::ACTIVE;
        $this->activatedAt = new DateTimeImmutable($event->activatedAt);
    }

    public function suspend(
        string $reason,
        int $suspensionDurationSeconds,
        ?DateTimeImmutable $suspendedAt = null
    ): void
    {
        $finalSuspendedAt = $suspendedAt ?? new DateTimeImmutable();

        if ($this->status !== GiftCardStatus::ACTIVE){
            throw GiftCardNotActiveException::create($this->status);
        }

        $this->apply(new GiftCardSuspended(
            $this->id->toString(),
            $reason,
            $finalSuspendedAt->format('Y-m-d\TH:i:s.uP'),
            $suspensionDurationSeconds
        ));
    }

    /**
     * @throws \DateMalformedStringException
     */
    protected function applyGiftCardSuspended(GiftCardSuspended $event): void
    {
        $this->status = GiftCardStatus::SUSPENDED;
        $this->suspendedAt = new DateTimeImmutable($event->suspendedAt);
        $this->suspensionDurationSeconds = $event->suspensionDurationSeconds;
    }
    public function reactivate(
        ?string $reason = null,
        ?DateTimeImmutable $reactivatedAt = null
    ): void
    {
        $finalReactivatedAt = $reactivatedAt ?? new DateTimeImmutable();

        if ($this->status !== GiftCardStatus::SUSPENDED){
            throw WrongGiftCardStatusException::create(GiftCardStatus::SUSPENDED, $this->status);
        }

        if ($this->suspensionDurationSeconds === null) {
            throw InvalidSuspensionStateException::suspensionDurationNotSet();
        }

        $newExpiresAt = $this->expiresAt?->modify("+{$this->suspensionDurationSeconds} seconds");

        $this->apply(new GiftCardReactivated(
            $this->id->toString(),
            $reason,
            $finalReactivatedAt->format('Y-m-d\TH:i:s.uP'),
            $newExpiresAt?->format('Y-m-d\TH:i:s.uP')
        ));
    }
    protected function applyGiftCardReactivated(GiftCardReactivated $event): void
    {
        $this->status = GiftCardStatus::ACTIVE;
        $this->expiresAt = $event->newExpiresAt ? new DateTimeImmutable($event->newExpiresAt) : null;
        $this->suspendedAt = null;
        $this->suspensionDurationSeconds = null;
    }
    public function cancel(
        ?string $reason = null,
        ?DateTimeImmutable $cancelledAt = null
    ): void
    {
        $finalCancelledAt = $cancelledAt ?? new DateTimeImmutable();

        if (!in_array($this->status, [GiftCardStatus::ACTIVE, GiftCardStatus::SUSPENDED], true)){
            throw WrongGiftCardStatusException::cannotCancel($this->status);
        }

        $this->apply(new GiftCardCancelled(
            $this->id->toString(),
            $reason,
            $finalCancelledAt->format('Y-m-d\TH:i:s.uP')
        ));
    }
    protected function applyGiftCardCancelled(GiftCardCancelled $event): void
    {
        $this->status = GiftCardStatus::CANCELLED;
        $this->cancelledAt = new DateTimeImmutable($event->cancelledAt);
    }
    public function expire(?DateTimeImmutable $expiredAt = null): void
    {
        $finalExpiredAt = $expiredAt ?? new DateTimeImmutable();

        if ($this->status !== GiftCardStatus::ACTIVE){
            throw WrongGiftCardStatusException::create(GiftCardStatus::ACTIVE, $this->status);
        }

        if ($this->expiresAt === null) {
            throw NoExpirationDateException::create();
        }

        if ($this->expiresAt > $finalExpiredAt){
            throw GiftCardNotExpiredException::create($this->expiresAt);
        }

        $this->apply(new GiftCardExpired(
            $this->id->toString(),
            $finalExpiredAt->format('Y-m-d\TH:i:s.uP')
        ));
    }
    protected function applyGiftCardExpired(GiftCardExpired $event): void
    {
        $this->status = GiftCardStatus::EXPIRED;
        $this->expiredAt = new DateTimeImmutable($event->expiredAt);
    }
    public function adjustBalance(
        Money $adjustment,
        string $reason,
        ?DateTimeImmutable $adjustedAt = null
    ): void
    {
        $finalAdjustedAt = $adjustedAt ?? new DateTimeImmutable();

        if ($this->status !== GiftCardStatus::ACTIVE){
            throw WrongGiftCardStatusException::create(GiftCardStatus::ACTIVE, $this->status);
        }

        $newBalance = $this->balance->add($adjustment);

        if ($newBalance->getAmount() < 0) {
            throw InsufficientBalanceException::notEnoughFunds($this->balance, $adjustment);
        }

        $this->apply(new GiftCardBalanceAdjusted(
            $this->id->toString(),
            $adjustment->getAmount(),
            $adjustment->getCurrency(),
            $reason,
            $finalAdjustedAt->format('Y-m-d\TH:i:s.uP')
        ));

        if ($this->balance->getAmount() === 0) {
            $this->apply(new GiftCardDepleted(
                $this->id->toString(),
                $finalAdjustedAt->format('Y-m-d\TH:i:s.uP')
            ));
        }
    }
    protected function applyGiftCardBalanceAdjusted(GiftCardBalanceAdjusted $event): void
    {
        $adjustment = Money::fromPrimitives($event->adjustmentAmount, $event->adjustmentCurrency);
        $this->balance = $this->balance->add($adjustment);
    }

    public function decreaseBalance(
        Money $amount,
        string $reason,
        ?DateTimeImmutable $decreasedAt = null
    ): void
    {
        $finalDecreasedAt = $decreasedAt ?? new DateTimeImmutable();

        if ($this->status !== GiftCardStatus::ACTIVE){
            throw WrongGiftCardStatusException::create(GiftCardStatus::ACTIVE, $this->status);
        }

        if (!$this->balance->isGreaterThanOrEqual($amount)){
            throw InsufficientBalanceException::notEnoughFunds($this->balance, $amount);
        }

        $this->apply(new GiftCardBalanceDecreased(
            $this->id->toString(),
            $amount->getAmount(),
            $amount->getCurrency(),
            $reason,
            $finalDecreasedAt->format('Y-m-d\TH:i:s.uP')
        ));

        if ($this->balance->getAmount() === 0) {
            $this->apply(new GiftCardDepleted(
                $this->id->toString(),
                $finalDecreasedAt->format('Y-m-d\TH:i:s.uP')
            ));
        }
    }

    protected function applyGiftCardBalanceDecreased(GiftCardBalanceDecreased $event): void
    {
        $decreaseAmount = Money::fromPrimitives($event->amount, $event->currency);
        $this->balance = $this->balance->subtract($decreaseAmount);
    }
}
