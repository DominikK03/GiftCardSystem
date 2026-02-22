<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Application\Handler;

use App\Application\GiftCard\Command\CreateCommand;
use App\Application\GiftCard\Handler\Create;
use App\Application\GiftCard\Port\GiftCardPersisterInterface;
use App\Domain\GiftCard\Aggregate\GiftCard;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Infrastructure\Tenant\TenantContext;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CreateTest extends TestCase
{
    public function test_it_creates_and_persists(): void
    {
        $persister = $this->createMock(GiftCardPersisterInterface::class);

        $persister
            ->expects($this->once())
            ->method('handle')
            ->with($this->isInstanceOf(GiftCard::class));

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext
            ->expects($this->once())
            ->method('getTenantId')
            ->willReturn(TenantId::fromString('550e8400-e29b-41d4-a716-446655440000'));

        $handler = new Create($persister, $tenantContext);

        $giftCardId = $handler->__invoke(new CreateCommand(
            500,
            'PLN'
        ));

        $this->assertTrue(Uuid::isValid($giftCardId));
    }

    public function test_it_creates_with_explicit_tenant_id(): void
    {
        $persister = $this->createMock(GiftCardPersisterInterface::class);

        $persister
            ->expects($this->once())
            ->method('handle')
            ->with($this->isInstanceOf(GiftCard::class));

        $tenantContext = $this->createMock(TenantContext::class);
        $tenantContext
            ->expects($this->never())
            ->method('getTenantId');

        $handler = new Create($persister, $tenantContext);

        $giftCardId = $handler->__invoke(new CreateCommand(
            amount: 1000,
            currency: 'PLN',
            tenantId: '550e8400-e29b-41d4-a716-446655440000'
        ));

        $this->assertTrue(Uuid::isValid($giftCardId));
    }
}
