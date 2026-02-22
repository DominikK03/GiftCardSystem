<?php

declare(strict_types=1);

namespace App\Infrastructure\GiftCard\Persistence\ReadModel;

use App\Application\GiftCard\Port\GiftCardReadModelRepositoryInterface;
use App\Application\GiftCard\Port\GiftCardReadModelWriterInterface;
use App\Application\GiftCard\ReadModel\GiftCardReadModel;
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
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class GiftCardReadModelProjection
{
    public function __construct(
        private readonly GiftCardReadModelRepositoryInterface $queryRepository,
        private readonly GiftCardReadModelWriterInterface $repository
    ) {}

    #[AsMessageHandler]
    public function onGiftCardCreated(GiftCardCreated $event): void
    {
        $readModel = new GiftCardReadModel(
            id: $event->id,
            tenantId: $event->tenantId,
            balanceAmount: $event->amount,
            balanceCurrency: $event->currency,
            initialAmount: $event->amount,
            initialCurrency: $event->currency,
            status: GiftCardStatus::INACTIVE->value,
            createdAt: new \DateTimeImmutable($event->createdAt),
            expiresAt: $event->expiresAt ? new \DateTimeImmutable($event->expiresAt) : null,
            cardNumber: $event->cardNumber,
            pin: $event->pin
        );

        $this->repository->save($readModel);
    }

    #[AsMessageHandler]
    public function onGiftCardActivated(GiftCardActivated $event): void
    {
        $readModel = $this->queryRepository->findById($event->id);

        if (!$readModel) {
            return;
        }

        $readModel->status = GiftCardStatus::ACTIVE->value;
        $readModel->activatedAt = new \DateTimeImmutable($event->activatedAt);
        $readModel->updateFromEvent();

        $this->repository->save($readModel);
    }

    #[AsMessageHandler]
    public function onGiftCardRedeemed(GiftCardRedeemed $event): void
    {
        $readModel = $this->queryRepository->findById($event->id);

        if (!$readModel) {
            return;
        }

        $readModel->balanceAmount -= $event->amount;
        $readModel->updateFromEvent();

        $this->repository->save($readModel);
    }

    #[AsMessageHandler]
    public function onGiftCardDepleted(GiftCardDepleted $event): void
    {
        $readModel = $this->queryRepository->findById($event->id);

        if (!$readModel) {
            return;
        }

        $readModel->status = GiftCardStatus::DEPLETED->value;
        $readModel->depletedAt = new \DateTimeImmutable($event->depletedAt);
        $readModel->updateFromEvent();

        $this->repository->save($readModel);
    }

    #[AsMessageHandler]
    public function onGiftCardSuspended(GiftCardSuspended $event): void
    {
        $readModel = $this->queryRepository->findById($event->id);

        if (!$readModel) {
            return;
        }

        $readModel->status = GiftCardStatus::SUSPENDED->value;
        $readModel->suspendedAt = new \DateTimeImmutable($event->suspendedAt);
        $readModel->suspensionDuration += $event->suspensionDurationSeconds;
        $readModel->updateFromEvent();

        $this->repository->save($readModel);
    }

    #[AsMessageHandler]
    public function onGiftCardReactivated(GiftCardReactivated $event): void
    {
        $readModel = $this->queryRepository->findById($event->id);

        if (!$readModel) {
            return;
        }

        $readModel->status = GiftCardStatus::ACTIVE->value;
        $readModel->suspendedAt = null;

        if ($event->newExpiresAt) {
            $readModel->expiresAt = new \DateTimeImmutable($event->newExpiresAt);
        }

        $readModel->updateFromEvent();

        $this->repository->save($readModel);
    }

    #[AsMessageHandler]
    public function onGiftCardCancelled(GiftCardCancelled $event): void
    {
        $readModel = $this->queryRepository->findById($event->id);

        if (!$readModel) {
            return;
        }

        $readModel->status = GiftCardStatus::CANCELLED->value;
        $readModel->cancelledAt = new \DateTimeImmutable($event->cancelledAt);
        $readModel->updateFromEvent();

        $this->repository->save($readModel);
    }

    #[AsMessageHandler]
    public function onGiftCardExpired(GiftCardExpired $event): void
    {
        $readModel = $this->queryRepository->findById($event->id);

        if (!$readModel) {
            return;
        }

        $readModel->status = GiftCardStatus::EXPIRED->value;
        $readModel->expiredAt = new \DateTimeImmutable($event->expiredAt);
        $readModel->updateFromEvent();

        $this->repository->save($readModel);
    }

    #[AsMessageHandler]
    public function onGiftCardBalanceAdjusted(GiftCardBalanceAdjusted $event): void
    {
        $readModel = $this->queryRepository->findById($event->id);

        if (!$readModel) {
            return;
        }

        $readModel->balanceAmount += $event->adjustmentAmount;
        $readModel->updateFromEvent();

        $this->repository->save($readModel);
    }

    #[AsMessageHandler]
    public function onGiftCardBalanceDecreased(GiftCardBalanceDecreased $event): void
    {
        $readModel = $this->queryRepository->findById($event->id);

        if (!$readModel) {
            return;
        }

        $readModel->balanceAmount -= $event->amount;
        $readModel->updateFromEvent();

        $this->repository->save($readModel);
    }
}
