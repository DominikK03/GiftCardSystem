<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Integration\Messenger;

use App\Application\GiftCard\Command\ActivateCommand;
use App\Application\GiftCard\Command\AdjustBalanceCommand;
use App\Application\GiftCard\Command\CancelCommand;
use App\Application\GiftCard\Command\DecreaseBalanceCommand;
use App\Application\GiftCard\Command\ExpireCommand;
use App\Application\GiftCard\Command\ReactivateCommand;
use App\Application\GiftCard\Command\RedeemCommand;
use App\Application\GiftCard\Command\SuspendCommand;
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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

final class MessengerRoutingTest extends KernelTestCase
{
    #[DataProvider('commandProvider')]
    public function test_commands_are_routed_to_async(object $command): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var InMemoryTransport $asyncTransport */
        $asyncTransport = $container->get('messenger.transport.async');
        /** @var InMemoryTransport $eventsTransport */
        $eventsTransport = $container->get('messenger.transport.async_events');
        /** @var SendersLocatorInterface $sendersLocator */
        $sendersLocator = $container->get('messenger.senders_locator');

        $senders = array_values(iterator_to_array($sendersLocator->getSenders(new Envelope($command))));
        $this->assertContains($asyncTransport, $senders);
        $this->assertNotContains($eventsTransport, $senders);
    }

    #[DataProvider('eventProvider')]
    public function test_events_are_routed_to_async_events(object $event): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var InMemoryTransport $asyncTransport */
        $asyncTransport = $container->get('messenger.transport.async');
        /** @var InMemoryTransport $eventsTransport */
        $eventsTransport = $container->get('messenger.transport.async_events');
        /** @var SendersLocatorInterface $sendersLocator */
        $sendersLocator = $container->get('messenger.senders_locator');

        $senders = array_values(iterator_to_array($sendersLocator->getSenders(new Envelope($event))));
        $this->assertContains($eventsTransport, $senders);
        $this->assertNotContains($asyncTransport, $senders);
    }

    public static function commandProvider(): array
    {
        $id = GiftCardId::generate()->toString();
        $timestamp = '2025-01-01T10:00:00+00:00';

        return [
            [new RedeemCommand($id, 100, 'PLN')],
            [new ActivateCommand($id, $timestamp)],
            [new SuspendCommand($id, 'Reason', $timestamp, 3600)],
            [new ReactivateCommand($id, 'Reason', $timestamp)],
            [new CancelCommand($id, 'Reason', $timestamp)],
            [new AdjustBalanceCommand($id, 200, 'PLN', 'Bonus', $timestamp)],
            [new DecreaseBalanceCommand($id, 100, 'PLN', 'Correction', $timestamp)],
            [new ExpireCommand($id, $timestamp)],
        ];
    }

    public static function eventProvider(): array
    {
        $id = GiftCardId::generate()->toString();
        $tenantId = '550e8400-e29b-41d4-a716-446655440000';
        $timestamp = '2025-01-01T10:00:00+00:00';

        return [
            [new GiftCardCreated($id, $tenantId, 1000, 'PLN', $timestamp, '2026-01-01T10:00:00+00:00')],
            [new GiftCardRedeemed($id, 100, 'PLN', $timestamp)],
            [new GiftCardActivated($id, $timestamp)],
            [new GiftCardSuspended($id, 'Reason', $timestamp, 3600)],
            [new GiftCardReactivated($id, 'Reason', $timestamp, '2026-02-01T10:00:00+00:00')],
            [new GiftCardCancelled($id, 'Reason', $timestamp)],
            [new GiftCardExpired($id, $timestamp)],
            [new GiftCardDepleted($id, $timestamp)],
            [new GiftCardBalanceAdjusted($id, 200, 'PLN', 'Bonus', $timestamp)],
            [new GiftCardBalanceDecreased($id, 150, 'PLN', 'Correction', $timestamp)],
        ];
    }
}
