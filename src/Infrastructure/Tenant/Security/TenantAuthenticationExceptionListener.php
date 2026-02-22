<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Security;

use App\Domain\Tenant\Exception\TenantAuthenticationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
class TenantAuthenticationExceptionListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof TenantAuthenticationException) {
            return;
        }

        $response = new JsonResponse(
            ['error' => $throwable->getMessage()],
            Response::HTTP_UNAUTHORIZED
        );

        $event->setResponse($response);
    }
}
