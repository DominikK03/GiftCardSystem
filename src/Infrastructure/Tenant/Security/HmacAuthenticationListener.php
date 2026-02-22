<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Security;

use App\Domain\Tenant\Enum\TenantStatus;
use App\Domain\Tenant\Exception\TenantAuthenticationException;
use App\Domain\Tenant\Exception\TenantNotFoundException;
use App\Domain\Tenant\Port\TenantQueryRepositoryInterface;
use App\Domain\Tenant\ValueObject\TenantId;
use App\Infrastructure\Tenant\TenantContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
class HmacAuthenticationListener implements EventSubscriberInterface
{
    private const HEADER_TENANT_ID = 'X-Tenant-Id';
    private const HEADER_TIMESTAMP = 'X-Timestamp';
    private const HEADER_SIGNATURE = 'X-Signature';

    public function __construct(
        private readonly HmacValidator $hmacValidator,
        private readonly TenantQueryRepositoryInterface $tenantRepository,
        private readonly TenantContext $tenantContext
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api/gift-cards')) {
            return;
        }

        $tenantIdHeader = $request->headers->get(self::HEADER_TENANT_ID);
        $timestamp = $request->headers->get(self::HEADER_TIMESTAMP);
        $signature = $request->headers->get(self::HEADER_SIGNATURE);

        if (!$tenantIdHeader || !$timestamp || !$signature) {
            throw TenantAuthenticationException::missingAuthenticationHeaders();
        }

        try {
            $tenantId = TenantId::fromString($tenantIdHeader);
            $tenant = $this->tenantRepository->findById($tenantId);
        } catch (TenantNotFoundException $e) {
            throw TenantAuthenticationException::tenantNotFound($tenantIdHeader);
        } catch (\Throwable $e) {
            throw TenantAuthenticationException::tenantNotFound($tenantIdHeader);
        }

        if ($tenant->getStatus() === TenantStatus::SUSPENDED) {
            throw TenantAuthenticationException::tenantSuspended();
        }

        if ($tenant->getStatus() === TenantStatus::CANCELLED) {
            throw TenantAuthenticationException::tenantCancelled();
        }

        $requestBody = $request->getContent();

        $this->hmacValidator->validateSignature(
            $tenantId,
            $timestamp,
            $requestBody,
            $signature,
            $tenant->getApiSecret()
        );

        $this->tenantContext->setTenantId($tenantId);
    }
}
