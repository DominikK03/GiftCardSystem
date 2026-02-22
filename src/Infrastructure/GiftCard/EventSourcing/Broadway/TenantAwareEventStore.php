<?php

declare(strict_types=1);

namespace App\Infrastructure\GiftCard\EventSourcing\Broadway;

use App\Domain\Tenant\Exception\TenantMismatchException;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Infrastructure\Tenant\TenantContext;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\EventStore\EventStore;
final class TenantAwareEventStore implements EventStore
{
    public function __construct(
        private readonly EventStore $innerEventStore,
        private readonly TenantContext $tenantContext
    ) {
    }

    public function load($id): DomainEventStream
    {
        $tenantId = $this->tenantContext->getTenantId();
        $stream = $this->innerEventStore->load($id);

        $this->verifyTenantOwnership($stream, $tenantId->toString(), (string) $id);

        return $stream;
    }

    public function loadFromPlayhead($id, int $playhead): DomainEventStream
    {
        $tenantId = $this->tenantContext->getTenantId();
        $stream = $this->innerEventStore->loadFromPlayhead($id, $playhead);

        $this->verifyTenantOwnership($stream, $tenantId->toString(), (string) $id);

        return $stream;
    }

    public function append($id, DomainEventStream $eventStream): void
    {
        $tenantId = $this->tenantContext->getTenantId();

        $enrichedStream = $this->enrichWithTenantId($eventStream, $tenantId->toString());

        $this->innerEventStore->append($id, $enrichedStream);
    }

    private function enrichWithTenantId(DomainEventStream $stream, string $tenantId): DomainEventStream
    {
        $enrichedMessages = [];

        foreach ($stream as $message) {
            /** @var DomainMessage $message */
            $metadata = $message->getMetadata()->serialize();
            $metadata['tenant_id'] = $tenantId;

            $enrichedMessages[] = new DomainMessage(
                $message->getId(),
                $message->getPlayhead(),
                new Metadata($metadata),
                $message->getPayload(),
                $message->getRecordedOn()
            );
        }

        return new DomainEventStream($enrichedMessages);
    }

    private function verifyTenantOwnership(DomainEventStream $stream, string $expectedTenantId, string $aggregateId): void
    {
        foreach ($stream as $message) {
            /** @var DomainMessage $message */
            $metadata = $message->getMetadata()->serialize();

            if (!isset($metadata['tenant_id'])) {
                throw TenantMismatchException::eventWithoutTenant($aggregateId);
            }

            if ($metadata['tenant_id'] !== $expectedTenantId) {
                throw TenantMismatchException::accessDenied(
                    $this->tenantContext->getTenantId(),
                    TenantId::fromString($metadata['tenant_id'])
                );
            }
        }
    }
}
