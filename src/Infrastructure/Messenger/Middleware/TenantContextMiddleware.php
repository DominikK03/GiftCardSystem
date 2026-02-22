<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Middleware;

use App\Domain\Tenant\ValueObject\TenantId;
use App\Infrastructure\Messenger\Stamp\TenantStamp;
use App\Infrastructure\Tenant\TenantContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

final class TenantContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->last(ReceivedStamp::class) !== null) {
            return $this->handleReceive($envelope, $stack);
        }

        if ($envelope->last(TenantStamp::class) === null && $this->tenantContext->hasTenant()) {
            $envelope = $envelope->with(
                new TenantStamp($this->tenantContext->getTenantId()->toString())
            );
        }

        return $stack->next()->handle($envelope, $stack);
    }

    private function handleReceive(Envelope $envelope, StackInterface $stack): Envelope
    {
        $previousTenantId = $this->tenantContext->hasTenant()
            ? $this->tenantContext->getTenantId()
            : null;

        $stamp = $envelope->last(TenantStamp::class);

        if ($stamp instanceof TenantStamp) {
            $this->tenantContext->setTenantId(
                TenantId::fromString($stamp->getTenantId())
            );
        }

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            if ($previousTenantId !== null) {
                $this->tenantContext->setTenantId($previousTenantId);
            } else {
                $this->tenantContext->clear();
            }
        }
    }
}
