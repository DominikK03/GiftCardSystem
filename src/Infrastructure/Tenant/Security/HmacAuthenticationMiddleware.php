<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class HmacAuthenticationMiddleware implements EventSubscriberInterface
{
    private const int MAX_TIMESTAMP_DIFF = 300;
    private const int NONCE_TTL = 300;

    public function __construct(
        private readonly TenantAuthenticator $authenticator,
        private readonly HmacSignatureVerifier $signatureVerifier,
        private readonly NonceStoreInterface $nonceStore
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Gift cards API has dedicated tenant HMAC auth via HmacAuthenticationListener.
        // Skip this generic middleware to avoid double-auth conflicts.
        if (str_starts_with($path, '/api/gift-cards')) {
            return;
        }

        if (in_array($path, ['/api/health', '/api/doc'], true)) {
            return;
        }

        $apiKey = $request->headers->get('X-Tenant-API-Key');
        $signature = $request->headers->get('X-Signature');
        $timestamp = $request->headers->get('X-Timestamp');
        $nonce = $request->headers->get('X-Nonce');

        if (!$apiKey || !$signature || !$timestamp || !$nonce) {
            $event->setResponse($this->errorResponse(
                'Missing authentication headers',
                Response::HTTP_UNAUTHORIZED
            ));
            return;
        }

        $now = time();
        $requestTime = (int) $timestamp;

        if (abs($now - $requestTime) > self::MAX_TIMESTAMP_DIFF) {
            $event->setResponse($this->errorResponse(
                'Request timestamp is too old or in the future',
                Response::HTTP_UNAUTHORIZED
            ));
            return;
        }

        if ($this->nonceStore->hasNonce($nonce)) {
            $event->setResponse($this->errorResponse(
                'Nonce already used (replay attack detected)',
                Response::HTTP_UNAUTHORIZED
            ));
            return;
        }

        try {
            $tenant = $this->authenticator->authenticate($apiKey);
        } catch (\Exception $e) {
            $event->setResponse($this->errorResponse(
                $e->getMessage(),
                Response::HTTP_UNAUTHORIZED
            ));
            return;
        }

        $isValid = $this->signatureVerifier->verify(
            providedSignature: $signature,
            method: $request->getMethod(),
            path: $request->getPathInfo(),
            timestamp: $timestamp,
            nonce: $nonce,
            body: $request->getContent(),
            secret: $tenant->getApiSecret()->toString()
        );

        if (!$isValid) {
            $event->setResponse($this->errorResponse(
                'Invalid signature',
                Response::HTTP_UNAUTHORIZED
            ));
            return;
        }

        $this->nonceStore->storeNonce($nonce, self::NONCE_TTL);

    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return new JsonResponse(
            ['error' => $message],
            $status
        );
    }
}
