<?php

declare(strict_types=1);

namespace App\Application\GiftCard\EventHandler;

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
use App\Domain\Tenant\Entity\TenantWebhook;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
final class TenantWebhookNotifier
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger
    ) {}

    #[AsMessageHandler]
    public function onGiftCardCreated(GiftCardCreated $event): void
    {
        $this->notify($event->tenantId, 'GiftCardCreated', [
            'giftCardId' => $event->id,
            'amount' => $event->amount,
            'currency' => $event->currency,
            'expiresAt' => $event->expiresAt,
            'createdAt' => $event->createdAt,
        ]);
    }

    #[AsMessageHandler]
    public function onGiftCardActivated(GiftCardActivated $event): void
    {
        $this->notifyByGiftCardId($event->id, 'GiftCardActivated', [
            'giftCardId' => $event->id,
            'activatedAt' => $event->activatedAt ?? '',
        ]);
    }

    #[AsMessageHandler]
    public function onGiftCardRedeemed(GiftCardRedeemed $event): void
    {
        $this->notifyByGiftCardId($event->id, 'GiftCardRedeemed', [
            'giftCardId' => $event->id,
            'amount' => $event->amount,
            'currency' => $event->currency,
            'redeemedAt' => $event->redeemedAt,
        ]);
    }

    #[AsMessageHandler]
    public function onGiftCardDepleted(GiftCardDepleted $event): void
    {
        $this->notifyByGiftCardId($event->id, 'GiftCardDepleted', [
            'giftCardId' => $event->id,
            'depletedAt' => $event->depletedAt,
        ]);
    }

    #[AsMessageHandler]
    public function onGiftCardSuspended(GiftCardSuspended $event): void
    {
        $this->notifyByGiftCardId($event->id, 'GiftCardSuspended', [
            'giftCardId' => $event->id,
            'reason' => $event->reason,
            'suspendedAt' => $event->suspendedAt,
        ]);
    }

    #[AsMessageHandler]
    public function onGiftCardReactivated(GiftCardReactivated $event): void
    {
        $this->notifyByGiftCardId($event->id, 'GiftCardReactivated', [
            'giftCardId' => $event->id,
            'newExpiresAt' => $event->newExpiresAt,
            'reactivatedAt' => $event->reactivatedAt,
        ]);
    }

    #[AsMessageHandler]
    public function onGiftCardCancelled(GiftCardCancelled $event): void
    {
        $this->notifyByGiftCardId($event->id, 'GiftCardCancelled', [
            'giftCardId' => $event->id,
            'reason' => $event->reason,
            'cancelledAt' => $event->cancelledAt,
        ]);
    }

    #[AsMessageHandler]
    public function onGiftCardExpired(GiftCardExpired $event): void
    {
        $this->notifyByGiftCardId($event->id, 'GiftCardExpired', [
            'giftCardId' => $event->id,
            'expiredAt' => $event->expiredAt,
        ]);
    }

    #[AsMessageHandler]
    public function onGiftCardBalanceAdjusted(GiftCardBalanceAdjusted $event): void
    {
        $this->notifyByGiftCardId($event->id, 'GiftCardBalanceAdjusted', [
            'giftCardId' => $event->id,
            'adjustmentAmount' => $event->adjustmentAmount,
            'currency' => $event->adjustmentCurrency,
            'reason' => $event->reason,
            'adjustedAt' => $event->adjustedAt,
        ]);
    }

    #[AsMessageHandler]
    public function onGiftCardBalanceDecreased(GiftCardBalanceDecreased $event): void
    {
        $this->notifyByGiftCardId($event->id, 'GiftCardBalanceDecreased', [
            'giftCardId' => $event->id,
            'amount' => $event->amount,
            'currency' => $event->currency,
            'decreasedAt' => $event->decreasedAt,
        ]);
    }

    private function notify(string $tenantId, string $eventType, array $payload): void
    {
        $webhooks = $this->entityManager
            ->getRepository(TenantWebhook::class)
            ->findBy(['tenantId' => $tenantId, 'isActive' => true]);

        foreach ($webhooks as $webhook) {
            if (!$webhook->supportsEvent($eventType)) {
                continue;
            }

            $this->sendWebhook($webhook, $eventType, $payload);
        }
    }
    private function notifyByGiftCardId(string $giftCardId, string $eventType, array $payload): void
    {
        $result = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT tenant_id FROM gift_cards_read WHERE id = :id',
            ['id' => $giftCardId]
        );

        if (!$result) {
            return;
        }

        $this->notify($result['tenant_id'], $eventType, $payload);
    }

    private function sendWebhook(TenantWebhook $webhook, string $eventType, array $payload): void
    {
        $body = json_encode([
            'event' => $eventType,
            'data' => $payload,
            'timestamp' => time(),
        ]);

        $signature = hash_hmac('sha256', $body, $webhook->getSecret());

        try {
            $this->httpClient->request('POST', $webhook->getUrl(), [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $eventType,
                ],
                'body' => $body,
                'timeout' => 10,
            ]);

            $this->logger->info('Webhook sent', [
                'tenantId' => $webhook->getTenantId(),
                'eventType' => $eventType,
                'url' => $webhook->getUrl(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Webhook delivery failed', [
                'tenantId' => $webhook->getTenantId(),
                'eventType' => $eventType,
                'url' => $webhook->getUrl(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
