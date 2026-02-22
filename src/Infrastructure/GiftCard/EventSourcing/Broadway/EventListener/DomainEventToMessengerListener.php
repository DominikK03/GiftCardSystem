<?php

declare(strict_types=1);

namespace App\Infrastructure\GiftCard\EventSourcing\Broadway\EventListener;

use Broadway\Domain\DomainMessage;
use Broadway\EventHandling\EventListener;
use Symfony\Component\Messenger\MessageBusInterface;
final class DomainEventToMessengerListener implements EventListener
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {}

    public function handle(DomainMessage $domainMessage): void
    {
        $event = $domainMessage->getPayload();

        $this->messageBus->dispatch($event);
    }
}
