<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tenant_webhooks')]
#[ORM\Index(columns: ['tenant_id'], name: 'idx_webhook_tenant_id')]
class TenantWebhook
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 36)]
    private string $tenantId;

    #[ORM\Column(type: 'string', length: 500)]
    private string $url;

    #[ORM\Column(type: 'json')]
    private array $events;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive;

    #[ORM\Column(type: 'string', length: 64)]
    private string $secret;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $tenantId,
        string $url,
        array $events,
        string $secret
    ) {
        $this->tenantId = $tenantId;
        $this->url = $url;
        $this->events = $events;
        $this->secret = $secret;
        $this->isActive = true;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function supportsEvent(string $eventType): bool
    {
        return in_array($eventType, $this->events, true) || in_array('*', $this->events, true);
    }
}
