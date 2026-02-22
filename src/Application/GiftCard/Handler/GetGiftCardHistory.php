<?php

declare(strict_types=1);

namespace App\Application\GiftCard\Handler;

use App\Application\GiftCard\View\GiftCardEventHistoryItem;
use App\Application\GiftCard\View\GiftCardHistoryView;
use App\Application\GiftCard\Query\GetGiftCardHistoryQuery;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use Broadway\Domain\DomainMessage;
use Broadway\EventStore\EventStreamNotFoundException;
use Broadway\EventStore\EventStore;
use ReflectionClass;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class  GetGiftCardHistory
{
    public function __construct(
        private readonly EventStore $eventStore
    ) {}

    public function __invoke(GetGiftCardHistoryQuery $query): ?GiftCardHistoryView
    {
        $giftCardId = GiftCardId::fromString($query->id);

        try {
            $eventStream = $this->eventStore->load($giftCardId->toString());
        } catch (EventStreamNotFoundException) {
            return null;
        }

        if ($eventStream->getIterator()->count() === 0) {
            return null;
        }

        $history = [];
        $eventNumber = 0;

        $giftCard = $this->createEmptyAggregate();

        foreach ($eventStream as $domainMessage) {
            $eventNumber++;

            $this->applyEventToAggregate($giftCard, $domainMessage);

            $state = $this->extractAggregateState($giftCard);

            $history[] = new GiftCardEventHistoryItem(
                eventType: $this->getEventShortName($domainMessage->getPayload()),
                eventNumber: $eventNumber,
                occurredAt: $domainMessage->getRecordedOn()->toString(),
                eventPayload: $this->serializeEventPayload($domainMessage->getPayload()),
                status: $state['status'],
                balanceAmount: $state['balanceAmount'],
                balanceCurrency: $state['balanceCurrency'],
                expiresAt: $state['expiresAt'],
                activatedAt: $state['activatedAt'],
                suspendedAt: $state['suspendedAt'],
                cancelledAt: $state['cancelledAt'],
                expiredAt: $state['expiredAt'],
                depletedAt: $state['depletedAt'],
                suspensionDuration: $state['suspensionDuration']
            );
        }

        return new GiftCardHistoryView(
            giftCardId: $query->id,
            history: $history
        );
    }

    private function createEmptyAggregate(): GiftCard
    {
        $reflection = new ReflectionClass(GiftCard::class);
        return $reflection->newInstanceWithoutConstructor();
    }

    private function applyEventToAggregate(GiftCard $aggregate, DomainMessage $domainMessage): void
    {
        $reflection = new ReflectionClass($aggregate);
        $parentReflection = $reflection->getParentClass();

        $handleMethod = $parentReflection->getMethod('handle');
        $handleMethod->setAccessible(true);
        $handleMethod->invoke($aggregate, $domainMessage->getPayload());
    }

    private function extractAggregateState(GiftCard $giftCard): array
    {
        $reflection = new ReflectionClass($giftCard);

        $balance = $this->getPropertyValue($reflection, $giftCard, 'balance');
        $status = $this->getPropertyValue($reflection, $giftCard, 'status');

        return [
            'status' => $status->value,
            'balanceAmount' => $balance->getAmount(),
            'balanceCurrency' => $balance->getCurrency(),
            'expiresAt' => $this->formatDate($this->getPropertyValue($reflection, $giftCard, 'expiresAt')),
            'activatedAt' => $this->formatDate($this->getPropertyValue($reflection, $giftCard, 'activatedAt')),
            'suspendedAt' => $this->formatDate($this->getPropertyValue($reflection, $giftCard, 'suspendedAt')),
            'cancelledAt' => $this->formatDate($this->getPropertyValue($reflection, $giftCard, 'cancelledAt')),
            'expiredAt' => $this->formatDate($this->getPropertyValue($reflection, $giftCard, 'expiredAt')),
            'depletedAt' => $this->formatDate($this->getPropertyValue($reflection, $giftCard, 'depletedAt')),
            'suspensionDuration' => $this->getPropertyValue($reflection, $giftCard, 'suspensionDurationSeconds') ?? 0
        ];
    }

    private function getPropertyValue(ReflectionClass $reflection, object $object, string $propertyName): mixed
    {
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }

    private function formatDate(?\DateTimeImmutable $date): ?string
    {
        return $date?->format('c');
    }

    private function getEventShortName(object $event): string
    {
        $className = get_class($event);
        $parts = explode('\\', $className);
        return end($parts);
    }

    private function serializeEventPayload(object $event): array
    {
        $reflection = new ReflectionClass($event);
        $properties = $reflection->getProperties();

        $payload = [];
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($event);

            if ($value instanceof \DateTimeImmutable) {
                $payload[$property->getName()] = $value->format('c');
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $payload[$property->getName()] = (string) $value;
            } else {
                $payload[$property->getName()] = $value;
            }
        }

        return $payload;
    }
}
