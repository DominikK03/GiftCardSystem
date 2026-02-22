<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

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
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class MercureEventPublisher
{
    public function __construct(private readonly HubInterface $hub) {}

    #[AsMessageHandler]
    public function onGiftCardCreated(GiftCardCreated $event): void
    {
        $this->publish($event->id, 'GiftCardCreated', 'INACTIVE', [
            'amount' => $event->amount,
            'currency' => $event->currency,
        ]);
    }

    #[AsMessageHandler]
    public function onGiftCardActivated(GiftCardActivated $event): void
    {
        $this->publish($event->id, 'GiftCardActivated', 'ACTIVE');
    }

    #[AsMessageHandler]
    public function onGiftCardRedeemed(GiftCardRedeemed $event): void
    {
        $this->publish($event->id, 'GiftCardRedeemed', 'ACTIVE', [
            'amount' => $event->amount,
        ]);
    }

    #[AsMessageHandler]
    public function onGiftCardDepleted(GiftCardDepleted $event): void
    {
        $this->publish($event->id, 'GiftCardDepleted', 'DEPLETED');
    }

    #[AsMessageHandler]
    public function onGiftCardSuspended(GiftCardSuspended $event): void
    {
        $this->publish($event->id, 'GiftCardSuspended', 'SUSPENDED');
    }

    #[AsMessageHandler]
    public function onGiftCardReactivated(GiftCardReactivated $event): void
    {
        $this->publish($event->id, 'GiftCardReactivated', 'ACTIVE');
    }

    #[AsMessageHandler]
    public function onGiftCardCancelled(GiftCardCancelled $event): void
    {
        $this->publish($event->id, 'GiftCardCancelled', 'CANCELLED');
    }

    #[AsMessageHandler]
    public function onGiftCardExpired(GiftCardExpired $event): void
    {
        $this->publish($event->id, 'GiftCardExpired', 'EXPIRED');
    }

    #[AsMessageHandler]
    public function onGiftCardBalanceAdjusted(GiftCardBalanceAdjusted $event): void
    {
        $this->publish($event->id, 'GiftCardBalanceAdjusted', 'ACTIVE', [
            'adjustmentAmount' => $event->adjustmentAmount,
        ]);
    }

    #[AsMessageHandler]
    public function onGiftCardBalanceDecreased(GiftCardBalanceDecreased $event): void
    {
        $this->publish($event->id, 'GiftCardBalanceDecreased', 'ACTIVE', [
            'amount' => $event->amount,
        ]);
    }

    private function publish(string $id, string $event, string $status, array $extra = []): void
    {
        $data = json_encode(array_merge([
            'event' => $event,
            'id' => $id,
            'status' => $status,
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ], $extra));

        $this->hub->publish(new Update(
            ["/giftcards/{$id}", '/dashboard'],
            $data
        ));
    }
}
