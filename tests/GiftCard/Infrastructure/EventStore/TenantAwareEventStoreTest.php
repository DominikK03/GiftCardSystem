<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Infrastructure\EventStore;

use App\Domain\Tenant\Exception\TenantContextNotSetException;
use App\Domain\Tenant\Exception\TenantMismatchException;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Infrastructure\GiftCard\EventSourcing\Broadway\TenantAwareEventStore;
use App\Infrastructure\Tenant\TenantContext;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\EventStore\EventStore;
use PHPUnit\Framework\TestCase;

final class TenantAwareEventStoreTest extends TestCase
{
    private EventStore $innerEventStore;
    private TenantContext $tenantContext;
    private TenantAwareEventStore $tenantAwareEventStore;

    protected function setUp(): void
    {
        $this->innerEventStore = $this->createMock(EventStore::class);
        $this->tenantContext = new TenantContext();
        $this->tenantAwareEventStore = new TenantAwareEventStore(
            $this->innerEventStore,
            $this->tenantContext
        );
    }

    public function test_append_adds_tenant_id_to_metadata(): void
    {
        $tenantId = TenantId::generate();
        $this->tenantContext->setTenantId($tenantId);

        $metadata = new Metadata(['key' => 'value']);
        $message = DomainMessage::recordNow('id-123', 0, $metadata, new \stdClass());
        $stream = new DomainEventStream([$message]);

        $this->innerEventStore
            ->expects($this->once())
            ->method('append')
            ->with(
                'id-123',
                $this->callback(function (DomainEventStream $enrichedStream) use ($tenantId) {
                    $messages = iterator_to_array($enrichedStream);
                    $firstMessage = $messages[0];
                    $metadata = $firstMessage->getMetadata()->serialize();

                    return $metadata['tenant_id'] === $tenantId->toString()
                        && $metadata['key'] === 'value';
                })
            );

        $this->tenantAwareEventStore->append('id-123', $stream);
    }

    public function test_append_throws_exception_when_tenant_not_set(): void
    {
        $metadata = new Metadata([]);
        $message = DomainMessage::recordNow('id-123', 0, $metadata, new \stdClass());
        $stream = new DomainEventStream([$message]);

        $this->expectException(TenantContextNotSetException::class);

        $this->tenantAwareEventStore->append('id-123', $stream);
    }

    public function test_load_verifies_tenant_ownership(): void
    {
        $tenantId = TenantId::generate();
        $this->tenantContext->setTenantId($tenantId);

        $metadata = new Metadata(['tenant_id' => $tenantId->toString()]);
        $message = DomainMessage::recordNow('id-123', 0, $metadata, new \stdClass());
        $stream = new DomainEventStream([$message]);

        $this->innerEventStore
            ->expects($this->once())
            ->method('load')
            ->with('id-123')
            ->willReturn($stream);

        $result = $this->tenantAwareEventStore->load('id-123');

        $this->assertInstanceOf(DomainEventStream::class, $result);
    }

    public function test_load_throws_exception_when_tenant_mismatch(): void
    {
        $tenantId = TenantId::generate();
        $otherTenantId = TenantId::generate();
        $this->tenantContext->setTenantId($tenantId);

        $metadata = new Metadata(['tenant_id' => $otherTenantId->toString()]);
        $message = DomainMessage::recordNow('id-123', 0, $metadata, new \stdClass());
        $stream = new DomainEventStream([$message]);

        $this->innerEventStore
            ->expects($this->once())
            ->method('load')
            ->with('id-123')
            ->willReturn($stream);

        $this->expectException(TenantMismatchException::class);

        $this->tenantAwareEventStore->load('id-123');
    }
}
