<?php

declare(strict_types=1);

namespace App\Tests\GiftCard\Application\EventHandler;

use App\Application\GiftCard\EventHandler\TenantWebhookNotifier;
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
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TenantWebhookNotifierTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private Connection $connection;
    private EntityRepository $webhookRepository;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private TenantWebhookNotifier $notifier;
    private string $giftCardId;
    private string $tenantId;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);
        $this->webhookRepository = $this->createMock(EntityRepository::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->entityManager->method('getConnection')->willReturn($this->connection);
        $this->entityManager->method('getRepository')
            ->with(TenantWebhook::class)
            ->willReturn($this->webhookRepository);

        $this->notifier = new TenantWebhookNotifier(
            $this->entityManager,
            $this->httpClient,
            $this->logger
        );

        $this->giftCardId = Uuid::uuid4()->toString();
        $this->tenantId = Uuid::uuid4()->toString();
    }

    public function test_sends_webhook_on_gift_card_created(): void
    {
        $webhook = $this->createWebhook(['GiftCardCreated']);
        $this->webhookRepository->method('findBy')->willReturn([$webhook]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://example.com/webhook',
                $this->callback(function (array $options) {
                    $body = json_decode($options['body'], true);
                    return $body['event'] === 'GiftCardCreated'
                        && $body['data']['giftCardId'] === $this->giftCardId
                        && $body['data']['amount'] === 10000
                        && $options['headers']['X-Webhook-Event'] === 'GiftCardCreated'
                        && !empty($options['headers']['X-Webhook-Signature']);
                })
            );

        $this->logger->expects($this->once())->method('info');

        $event = new GiftCardCreated(
            $this->giftCardId,
            $this->tenantId,
            10000,
            'PLN',
            (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'),
            (new \DateTimeImmutable('+1 year'))->format('Y-m-d\TH:i:s.uP')
        );

        $this->notifier->onGiftCardCreated($event);
    }

    public function test_resolves_tenant_id_from_read_model_for_activated(): void
    {
        $this->mockTenantIdResolution();
        $webhook = $this->createWebhook(['GiftCardActivated']);
        $this->webhookRepository->method('findBy')->willReturn([$webhook]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://example.com/webhook',
                $this->callback(function (array $options) {
                    $body = json_decode($options['body'], true);
                    return $body['event'] === 'GiftCardActivated'
                        && $body['data']['giftCardId'] === $this->giftCardId;
                })
            );

        $event = new GiftCardActivated(
            $this->giftCardId,
            (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP')
        );

        $this->notifier->onGiftCardActivated($event);
    }

    public function test_skips_webhook_when_event_not_supported(): void
    {
        $webhook = $this->createWebhook(['GiftCardRedeemed']);
        $this->webhookRepository->method('findBy')->willReturn([$webhook]);

        $this->httpClient->expects($this->never())->method('request');

        $event = new GiftCardCreated(
            $this->giftCardId,
            $this->tenantId,
            10000,
            'PLN',
            (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'),
            (new \DateTimeImmutable('+1 year'))->format('Y-m-d\TH:i:s.uP')
        );

        $this->notifier->onGiftCardCreated($event);
    }

    public function test_wildcard_webhook_receives_all_events(): void
    {
        $webhook = $this->createWebhook(['*']);
        $this->webhookRepository->method('findBy')->willReturn([$webhook]);

        $this->httpClient->expects($this->once())->method('request');

        $event = new GiftCardCreated(
            $this->giftCardId,
            $this->tenantId,
            10000,
            'PLN',
            (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'),
            (new \DateTimeImmutable('+1 year'))->format('Y-m-d\TH:i:s.uP')
        );

        $this->notifier->onGiftCardCreated($event);
    }

    public function test_no_webhook_sent_when_no_active_webhooks(): void
    {
        $this->webhookRepository->method('findBy')->willReturn([]);

        $this->httpClient->expects($this->never())->method('request');

        $event = new GiftCardCreated(
            $this->giftCardId,
            $this->tenantId,
            10000,
            'PLN',
            (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'),
            (new \DateTimeImmutable('+1 year'))->format('Y-m-d\TH:i:s.uP')
        );

        $this->notifier->onGiftCardCreated($event);
    }

    public function test_does_not_send_webhook_when_read_model_not_found(): void
    {
        $this->connection->method('fetchAssociative')->willReturn(false);
        $this->httpClient->expects($this->never())->method('request');

        $event = new GiftCardActivated(
            $this->giftCardId,
            (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP')
        );

        $this->notifier->onGiftCardActivated($event);
    }

    public function test_logs_error_when_webhook_delivery_fails(): void
    {
        $webhook = $this->createWebhook(['GiftCardCreated']);
        $this->webhookRepository->method('findBy')->willReturn([$webhook]);

        $this->httpClient->method('request')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Webhook delivery failed', $this->callback(function (array $context) {
                return $context['error'] === 'Connection refused'
                    && $context['url'] === 'https://example.com/webhook';
            }));

        $event = new GiftCardCreated(
            $this->giftCardId,
            $this->tenantId,
            10000,
            'PLN',
            (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'),
            (new \DateTimeImmutable('+1 year'))->format('Y-m-d\TH:i:s.uP')
        );

        $this->notifier->onGiftCardCreated($event);
    }

    public function test_webhook_signature_is_hmac_sha256(): void
    {
        $webhook = $this->createWebhook(['GiftCardCreated']);
        $this->webhookRepository->method('findBy')->willReturn([$webhook]);

        $capturedOptions = null;
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedOptions) {
                $capturedOptions = $options;
                return $this->createMock(ResponseInterface::class);
            });

        $event = new GiftCardCreated(
            $this->giftCardId,
            $this->tenantId,
            10000,
            'PLN',
            (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'),
            (new \DateTimeImmutable('+1 year'))->format('Y-m-d\TH:i:s.uP')
        );

        $this->notifier->onGiftCardCreated($event);

        $expectedSignature = hash_hmac('sha256', $capturedOptions['body'], 'webhook-secret-123');
        $this->assertSame($expectedSignature, $capturedOptions['headers']['X-Webhook-Signature']);
    }

    public function test_sends_webhook_on_redeemed(): void
    {
        $this->mockTenantIdResolution();
        $webhook = $this->createWebhook(['GiftCardRedeemed']);
        $this->webhookRepository->method('findBy')->willReturn([$webhook]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://example.com/webhook', $this->callback(function (array $options) {
                $body = json_decode($options['body'], true);
                return $body['event'] === 'GiftCardRedeemed'
                    && $body['data']['amount'] === 5000;
            }));

        $event = new GiftCardRedeemed($this->giftCardId, 5000, 'PLN', (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'));
        $this->notifier->onGiftCardRedeemed($event);
    }

    public function test_sends_webhook_on_depleted(): void
    {
        $this->mockTenantIdResolution();
        $webhook = $this->createWebhook(['*']);
        $this->webhookRepository->method('findBy')->willReturn([$webhook]);

        $this->httpClient->expects($this->once())->method('request');

        $event = new GiftCardDepleted($this->giftCardId, (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'));
        $this->notifier->onGiftCardDepleted($event);
    }

    public function test_sends_webhook_on_suspended(): void
    {
        $this->mockTenantIdResolution();
        $webhook = $this->createWebhook(['GiftCardSuspended']);
        $this->webhookRepository->method('findBy')->willReturn([$webhook]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://example.com/webhook', $this->callback(function (array $options) {
                $body = json_decode($options['body'], true);
                return $body['data']['reason'] === 'Fraud';
            }));

        $event = new GiftCardSuspended($this->giftCardId, 'Fraud', (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'), 86400);
        $this->notifier->onGiftCardSuspended($event);
    }

    public function test_sends_webhook_on_reactivated(): void
    {
        $this->mockTenantIdResolution();
        $webhook = $this->createWebhook(['*']);
        $this->webhookRepository->method('findBy')->willReturn([$webhook]);

        $this->httpClient->expects($this->once())->method('request');

        $event = new GiftCardReactivated($this->giftCardId, null, (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'), null);
        $this->notifier->onGiftCardReactivated($event);
    }

    public function test_sends_webhook_on_cancelled(): void
    {
        $this->mockTenantIdResolution();
        $webhook = $this->createWebhook(['GiftCardCancelled']);
        $this->webhookRepository->method('findBy')->willReturn([$webhook]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://example.com/webhook', $this->callback(function (array $options) {
                $body = json_decode($options['body'], true);
                return $body['data']['reason'] === 'Refund';
            }));

        $event = new GiftCardCancelled($this->giftCardId, 'Refund', (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'));
        $this->notifier->onGiftCardCancelled($event);
    }

    public function test_sends_webhook_on_expired(): void
    {
        $this->mockTenantIdResolution();
        $webhook = $this->createWebhook(['*']);
        $this->webhookRepository->method('findBy')->willReturn([$webhook]);

        $this->httpClient->expects($this->once())->method('request');

        $event = new GiftCardExpired($this->giftCardId, (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'));
        $this->notifier->onGiftCardExpired($event);
    }

    public function test_sends_webhook_on_balance_adjusted(): void
    {
        $this->mockTenantIdResolution();
        $webhook = $this->createWebhook(['GiftCardBalanceAdjusted']);
        $this->webhookRepository->method('findBy')->willReturn([$webhook]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://example.com/webhook', $this->callback(function (array $options) {
                $body = json_decode($options['body'], true);
                return $body['data']['adjustmentAmount'] === -3000
                    && $body['data']['reason'] === 'Refund';
            }));

        $event = new GiftCardBalanceAdjusted($this->giftCardId, -3000, 'PLN', 'Refund', (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'));
        $this->notifier->onGiftCardBalanceAdjusted($event);
    }

    public function test_sends_webhook_on_balance_decreased(): void
    {
        $this->mockTenantIdResolution();
        $webhook = $this->createWebhook(['GiftCardBalanceDecreased']);
        $this->webhookRepository->method('findBy')->willReturn([$webhook]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://example.com/webhook', $this->callback(function (array $options) {
                $body = json_decode($options['body'], true);
                return $body['data']['amount'] === 2000;
            }));

        $event = new GiftCardBalanceDecreased($this->giftCardId, 2000, 'PLN', 'Correction', (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.uP'));
        $this->notifier->onGiftCardBalanceDecreased($event);
    }

    private function createWebhook(array $events): TenantWebhook
    {
        return new TenantWebhook(
            $this->tenantId,
            'https://example.com/webhook',
            $events,
            'webhook-secret-123'
        );
    }

    private function mockTenantIdResolution(): void
    {
        $this->connection->method('fetchAssociative')
            ->with(
                'SELECT tenant_id FROM gift_cards_read WHERE id = :id',
                ['id' => $this->giftCardId]
            )
            ->willReturn(['tenant_id' => $this->tenantId]);
    }
}
