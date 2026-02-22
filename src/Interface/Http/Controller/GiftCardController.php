<?php

declare(strict_types=1);

namespace App\Interface\Http\Controller;

use App\Application\GiftCard\Command\ActivateCommand;
use App\Application\GiftCard\Command\AdjustBalanceCommand;
use App\Application\GiftCard\Command\CancelCommand;
use App\Application\GiftCard\Command\CreateCommand;
use App\Application\GiftCard\Command\DecreaseBalanceCommand;
use App\Application\GiftCard\Command\ExpireCommand;
use App\Application\GiftCard\Command\ReactivateCommand;
use App\Application\GiftCard\Command\RedeemCommand;
use App\Application\GiftCard\Command\SuspendCommand;
use App\Application\GiftCard\Query\GetGiftCardHistoryQuery;
use App\Application\GiftCard\Query\GetGiftCardQuery;
use App\Application\GiftCard\Query\GetGiftCardsQuery;
use App\Infrastructure\Tenant\TenantContext;
use App\Interface\Http\Request\ActivateGiftCardRequest;
use App\Interface\Http\Request\AdjustBalanceRequest;
use App\Interface\Http\Request\CancelGiftCardRequest;
use App\Interface\Http\Request\CreateGiftCardRequest;
use App\Interface\Http\Request\DecreaseBalanceRequest;
use App\Interface\Http\Request\ExpireGiftCardRequest;
use App\Interface\Http\Request\ReactivateGiftCardRequest;
use App\Interface\Http\Request\RedeemGiftCardRequest;
use App\Interface\Http\Request\SuspendGiftCardRequest;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Gift Cards')]
#[Route('/api/gift-cards', name: 'api_gift_cards_')]
final class GiftCardController extends AbstractController
{
    use JsonValidationTrait;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly ValidatorInterface $validator,
        private readonly TenantContext $tenantContext
    ) {}

    #[Route('/create', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/gift-cards/create',
        summary: 'Create a new Gift Card',
        security: [['TenantId' => [], 'TenantTimestamp' => [], 'TenantSignature' => []]],
        requestBody: new OA\RequestBody(
            description: 'Gift Card data',
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'currency'],
                properties: [
                    new OA\Property(property: 'amount', description: 'Amount in smallest currency unit (grosze)', type: 'integer', example: 100000),
                    new OA\Property(property: 'currency', type: 'string', example: 'PLN'),
                    new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', example: '2026-12-31T23:59:59+00:00', nullable: true)
                ]
            )
        ),
        tags: ['Gift Cards'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Gift Card created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Gift card created successfully'),
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Generated Gift Card UUID'),
                        new OA\Property(property: 'status', type: 'string', example: 'created')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 401, description: 'HMAC authentication failed (missing headers, invalid signature, expired timestamp, or suspended tenant)'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $this->validateAndDecodeJson($request);
            $dto = CreateGiftCardRequest::fromArray($data);
            $this->validateDto($dto, $this->validator);

            $expiresAt = $this->normalizeIsoDate($dto->expiresAt);

            $command = new CreateCommand(
                amount: (int) $dto->amount,
                currency: (string) $dto->currency,
                expiresAt: $expiresAt
            );

            $envelope = $this->messageBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);
            $giftCardId = $handledStamp?->getResult();

            return new JsonResponse([
                'message' => 'Gift card created successfully',
                'id' => $giftCardId,
                'status' => 'created'
            ], Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/{id}/redeem', name: 'redeem', methods: ['POST'])]
    #[OA\Post(
        path: '/api/gift-cards/{id}/redeem',
        summary: 'Redeem (use) a Gift Card',
        security: [['TenantId' => [], 'TenantTimestamp' => [], 'TenantSignature' => []]],
        requestBody: new OA\RequestBody(
            description: 'Redemption data',
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'currency'],
                properties: [
                    new OA\Property(property: 'amount', description: 'Amount to redeem in smallest currency unit', type: 'integer', example: 5000),
                    new OA\Property(property: 'currency', type: 'string', example: 'PLN')
                ]
            )
        ),
        tags: ['Gift Cards'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Redeem command accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'status', type: 'string')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 401, description: 'HMAC authentication failed'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function redeem(string $id, Request $request): JsonResponse
    {
        try {
            $this->assertValidUuid($id);
            $data = $this->validateAndDecodeJson($request);
            $dto = RedeemGiftCardRequest::fromArray($data);
            $this->validateDto($dto, $this->validator);

            $command = new RedeemCommand(
                giftCardId: $id,
                amount: (int) $dto->amount,
                currency: (string) $dto->currency
            );

            $this->messageBus->dispatch($command);

            return new JsonResponse([
                'message' => 'Gift card redeem command dispatched',
                'id' => $id,
                'status' => 'pending'
            ], Response::HTTP_ACCEPTED);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/health', name: 'health', methods: ['GET'])]
    #[OA\Get(
        path: '/api/gift-cards/health',
        summary: 'Health check endpoint',
        tags: ['Gift Cards'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service is healthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'service', type: 'string', example: 'Gift Card API'),
                        new OA\Property(property: 'note', type: 'string')
                    ]
                )
            )
        ]
    )]
    public function health(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'service' => 'Gift Card API',
            'note' => 'Commands are processed asynchronously via RabbitMQ'
        ]);
    }

    #[Route('/{id}/activate', name: 'activate', methods: ['POST'])]
    #[OA\Post(
        path: '/api/gift-cards/{id}/activate',
        summary: 'Activate a Gift card',
        security: [['TenantId' => [], 'TenantTimestamp' => [], 'TenantSignature' => []]],
        requestBody: new OA\RequestBody(
            description: 'Activation data',
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'activatedAt', type: 'string', format: 'date-time', example: '2025-01-01T10:00:00+00:00', nullable: true)
                ]
            )
        ),
        tags: ['Gift Cards'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Activate command accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'status', type: 'string')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'HMAC authentication failed'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function activate(string $id, Request $request): JsonResponse
    {
        try {
            $this->assertValidUuid($id);
            $data = $this->validateAndDecodeJson($request);
            $dto = ActivateGiftCardRequest::fromArray($data);
            $this->validateDto($dto, $this->validator);

            $activatedAt = $this->normalizeIsoDate($dto->activatedAt) ?? (new \DateTimeImmutable())->format('c');
            $command = new ActivateCommand(
                id: $id,
                activatedAt: $activatedAt
            );
            $this->messageBus->dispatch($command);

            return new JsonResponse([
                'message' => 'Gift card activate command dispatched',
                'id' => $id,
                'status' => 'pending'
            ], Response::HTTP_ACCEPTED);
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/{id}/suspend', name: 'suspend', methods: ['POST'])]
    #[OA\Post(
        path: '/api/gift-cards/{id}/suspend',
        summary: 'Suspend a Gift card',
        security: [['TenantId' => [], 'TenantTimestamp' => [], 'TenantSignature' => []]],
        requestBody: new OA\RequestBody(
            description: 'Suspension data',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string', nullable: false),
                    new OA\Property(property: 'suspendedAt', type: 'string', format: 'date-time', example: '2025-01-01T10:00:00+00:00', nullable: true),
                    new OA\Property(property: 'suspensionDurationSeconds', type: 'int', nullable: true)
                ]
            )
        ),
        tags: ['Gift Cards'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Suspend command accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'status', type: 'string')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 401, description: 'HMAC authentication failed'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function suspend(string $id, Request $request): JsonResponse
    {
        try {
            $this->assertValidUuid($id);
            $data = $this->validateAndDecodeJson($request);
            $dto = SuspendGiftCardRequest::fromArray($data);
            $this->validateDto($dto, $this->validator);

            $suspendedAt = $this->normalizeIsoDate($dto->suspendedAt) ?? (new \DateTimeImmutable())->format('c');
            $command = new SuspendCommand(
                $id,
                (string) $dto->reason,
                $suspendedAt,
                (int) $dto->suspensionDurationSeconds
            );

            $this->messageBus->dispatch($command);

            return new JsonResponse([
                'message' => 'Gift card suspend command dispatched',
                'id' => $id,
                'status' => 'pending'
            ], Response::HTTP_ACCEPTED);
        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/{id}/reactivate', name: 'reactivate', methods: ['POST'])]
    #[OA\Post(
        path: '/api/gift-cards/{id}/reactivate',
        summary: 'Reactivate a suspended Gift Card',
        security: [['TenantId' => [], 'TenantTimestamp' => [], 'TenantSignature' => []]],
        requestBody: new OA\RequestBody(
            description: 'Reactivation data',
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string', nullable: true),
                    new OA\Property(property: 'reactivatedAt', type: 'string', format: 'date-time', example: '2025-01-15T10:00:00+00:00', nullable: true)
                ]
            )
        ),
        tags: ['Gift Cards'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Reactivate command accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'status', type: 'string')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'HMAC authentication failed'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function reactivate(string $id, Request $request): JsonResponse
    {
        try {
            $this->assertValidUuid($id);
            $data = $this->validateAndDecodeJson($request);
            $dto = ReactivateGiftCardRequest::fromArray($data);
            $this->validateDto($dto, $this->validator);

            $reactivatedAt = $this->normalizeIsoDate($dto->reactivatedAt) ?? (new \DateTimeImmutable())->format('c');
            $command = new ReactivateCommand(
                id: $id,
                reason: $dto->reason !== null ? (string) $dto->reason : null,
                reactivatedAt: $reactivatedAt
            );

            $this->messageBus->dispatch($command);

            return new JsonResponse([
                'message' => 'Gift card reactivate command dispatched',
                'id' => $id,
                'status' => 'pending'
            ], Response::HTTP_ACCEPTED);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    #[OA\Post(
        path: '/api/gift-cards/{id}/cancel',
        summary: 'Cancel a Gift Card',
        security: [['TenantId' => [], 'TenantTimestamp' => [], 'TenantSignature' => []]],
        requestBody: new OA\RequestBody(
            description: 'Cancellation data',
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string', nullable: true),
                    new OA\Property(property: 'cancelledAt', type: 'string', format: 'date-time', example: '2025-01-20T10:00:00+00:00', nullable: true)
                ]
            )
        ),
        tags: ['Gift Cards'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Cancel command accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'status', type: 'string')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'HMAC authentication failed'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function cancel(string $id, Request $request): JsonResponse
    {
        try {
            $this->assertValidUuid($id);
            $data = $this->validateAndDecodeJson($request);
            $dto = CancelGiftCardRequest::fromArray($data);
            $this->validateDto($dto, $this->validator);

            $cancelledAt = $this->normalizeIsoDate($dto->cancelledAt) ?? (new \DateTimeImmutable())->format('c');
            $command = new CancelCommand(
                id: $id,
                reason: $dto->reason !== null ? (string) $dto->reason : null,
                cancelledAt: $cancelledAt
            );

            $this->messageBus->dispatch($command);

            return new JsonResponse([
                'message' => 'Gift card cancel command dispatched',
                'id' => $id,
                'status' => 'pending'
            ], Response::HTTP_ACCEPTED);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/{id}/adjust-balance', name: 'adjust_balance', methods: ['POST'])]
    #[OA\Post(
        path: '/api/gift-cards/{id}/adjust-balance',
        summary: 'Adjust Gift Card balance (add or subtract amount)',
        security: [['TenantId' => [], 'TenantTimestamp' => [], 'TenantSignature' => []]],
        requestBody: new OA\RequestBody(
            description: 'Balance adjustment data',
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'currency', 'reason'],
                properties: [
                    new OA\Property(property: 'amount', description: 'Amount to adjust in smallest currency unit (can be negative)', type: 'integer', example: -5000),
                    new OA\Property(property: 'currency', type: 'string', example: 'PLN'),
                    new OA\Property(property: 'reason', type: 'string', example: 'Refund due to partial service'),
                    new OA\Property(property: 'adjustedAt', type: 'string', format: 'date-time', example: '2025-01-25T10:00:00+00:00', nullable: true)
                ]
            )
        ),
        tags: ['Gift Cards'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Adjust balance command accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'status', type: 'string')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 401, description: 'HMAC authentication failed'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function adjustBalance(string $id, Request $request): JsonResponse
    {
        try {
            $this->assertValidUuid($id);
            $data = $this->validateAndDecodeJson($request);
            $dto = AdjustBalanceRequest::fromArray($data);
            $this->validateDto($dto, $this->validator);

            $adjustedAt = $this->normalizeIsoDate($dto->adjustedAt) ?? (new \DateTimeImmutable())->format('c');
            $command = new AdjustBalanceCommand(
                id: $id,
                amount: (int) $dto->amount,
                currency: (string) $dto->currency,
                reason: (string) $dto->reason,
                adjustedAt: $adjustedAt
            );

            $this->messageBus->dispatch($command);

            return new JsonResponse([
                'message' => 'Gift card adjust balance command dispatched',
                'id' => $id,
                'status' => 'pending'
            ], Response::HTTP_ACCEPTED);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/{id}/decrease-balance', name: 'decrease_balance', methods: ['POST'])]
    #[OA\Post(
        path: '/api/gift-cards/{id}/decrease-balance',
        summary: 'Decrease Gift Card balance (admin adjustment)',
        security: [['TenantId' => [], 'TenantTimestamp' => [], 'TenantSignature' => []]],
        requestBody: new OA\RequestBody(
            description: 'Balance decrease data',
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'currency', 'reason'],
                properties: [
                    new OA\Property(property: 'amount', description: 'Amount to decrease in smallest currency unit', type: 'integer', example: 5000),
                    new OA\Property(property: 'currency', type: 'string', example: 'PLN'),
                    new OA\Property(property: 'reason', type: 'string', example: 'Manual correction'),
                    new OA\Property(property: 'decreasedAt', type: 'string', format: 'date-time', example: '2025-01-25T10:00:00+00:00', nullable: true)
                ]
            )
        ),
        tags: ['Gift Cards'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Decrease balance command accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'status', type: 'string')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 401, description: 'HMAC authentication failed'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function decreaseBalance(string $id, Request $request): JsonResponse
    {
        try {
            $this->assertValidUuid($id);
            $data = $this->validateAndDecodeJson($request);
            $dto = DecreaseBalanceRequest::fromArray($data);
            $this->validateDto($dto, $this->validator);

            $decreasedAt = $this->normalizeIsoDate($dto->decreasedAt) ?? (new \DateTimeImmutable())->format('c');
            $command = new DecreaseBalanceCommand(
                id: $id,
                amount: (int) $dto->amount,
                currency: (string) $dto->currency,
                reason: (string) $dto->reason,
                decreasedAt: $decreasedAt
            );

            $this->messageBus->dispatch($command);

            return new JsonResponse([
                'message' => 'Gift card decrease balance command dispatched',
                'id' => $id,
                'status' => 'pending'
            ], Response::HTTP_ACCEPTED);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/{id}/expire', name: 'expire', methods: ['POST'])]
    #[OA\Post(
        path: '/api/gift-cards/{id}/expire',
        summary: 'Expire a Gift Card',
        security: [['TenantId' => [], 'TenantTimestamp' => [], 'TenantSignature' => []]],
        requestBody: new OA\RequestBody(
            description: 'Expiration data',
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'expiredAt', type: 'string', format: 'date-time', example: '2025-12-31T23:59:59+00:00', nullable: true)
                ]
            )
        ),
        tags: ['Gift Cards'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Expire command accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'status', type: 'string')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'HMAC authentication failed'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function expire(string $id, Request $request): JsonResponse
    {
        try {
            $this->assertValidUuid($id);
            $data = $this->validateAndDecodeJson($request);
            $dto = ExpireGiftCardRequest::fromArray($data);
            $this->validateDto($dto, $this->validator);

            $expiredAt = $this->normalizeIsoDate($dto->expiredAt) ?? (new \DateTimeImmutable())->format('c');
            $command = new ExpireCommand(
                id: $id,
                expiredAt: $expiredAt
            );

            $this->messageBus->dispatch($command);

            return new JsonResponse([
                'message' => 'Gift card expire command dispatched',
                'id' => $id,
                'status' => 'pending'
            ], Response::HTTP_ACCEPTED);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/gift-cards',
        summary: 'List Gift Cards for current tenant',
        security: [['TenantId' => [], 'TenantTimestamp' => [], 'TenantSignature' => []]],
        tags: ['Gift Cards'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Page number',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                description: 'Items per page',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1)
            ),
            new OA\Parameter(
                name: 'status',
                description: 'Filter by status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['INACTIVE', 'ACTIVE', 'EXPIRED', 'DEPLETED', 'CANCELLED', 'SUSPENDED'])
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of Gift Cards',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'giftCards', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'total', type: 'integer', description: 'Total number of gift cards'),
                        new OA\Property(property: 'page', type: 'integer', description: 'Current page'),
                        new OA\Property(property: 'limit', type: 'integer', description: 'Items per page'),
                        new OA\Property(property: 'totalPages', type: 'integer', description: 'Total pages')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'HMAC authentication failed')
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
            $status = $request->query->get('status');

            $query = new GetGiftCardsQuery(
                tenantId: $this->tenantContext->getTenantId()->toString(),
                page: $page,
                limit: $limit,
                status: $status
            );

            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);
            $result = $handledStamp?->getResult();

            $result['giftCards'] = array_map(
                fn($view) => $view->toArray(),
                $result['giftCards']
            );

            return new JsonResponse($result);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/gift-cards/{id}',
        summary: 'Get Gift Card details',
        security: [['TenantId' => [], 'TenantTimestamp' => [], 'TenantSignature' => []]],
        tags: ['Gift Cards'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Gift Card UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Gift Card details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(
                            property: 'balance',
                            properties: [
                                new OA\Property(property: 'amount', description: 'Amount in smallest unit', type: 'integer'),
                                new OA\Property(property: 'currency', type: 'string'),
                                new OA\Property(property: 'formatted', type: 'string', example: '950.00 PLN')
                            ],
                            type: 'object'
                        ),
                        new OA\Property(
                            property: 'initialAmount',
                            properties: [
                                new OA\Property(property: 'amount', type: 'integer'),
                                new OA\Property(property: 'currency', type: 'string'),
                                new OA\Property(property: 'formatted', type: 'string')
                            ],
                            type: 'object'
                        ),
                        new OA\Property(property: 'status', type: 'string', example: 'ACTIVE'),
                        new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'activatedAt', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'suspendedAt', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'cancelledAt', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'expiredAt', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'depletedAt', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'suspensionDuration', description: 'Total suspension time in seconds', type: 'integer'),
                        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'HMAC authentication failed'),
            new OA\Response(response: 404, description: 'Gift Card not found'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function getGiftCard(string $id): JsonResponse
    {
        try {
            $this->assertValidUuid($id);
            $query = new GetGiftCardQuery($id);

            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);
            $giftCardView = $handledStamp?->getResult();

            if (!$giftCardView) {
                return new JsonResponse([
                    'error' => 'Gift Card not found'
                ], Response::HTTP_NOT_FOUND);
            }

            return new JsonResponse($giftCardView->toArray());

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/{id}/history', name: 'history', methods: ['GET'])]
    #[OA\Get(
        path: '/api/gift-cards/{id}/history',
        description: 'Returns all events that occurred for this Gift Card and the state of the aggregate after each event. This demonstrates Event Sourcing by replaying events.',
        summary: 'Get Gift Card event history with state changes',
        security: [['TenantId' => [], 'TenantTimestamp' => [], 'TenantSignature' => []]],
        tags: ['Gift Cards'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Gift Card UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Gift Card event history',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'giftCardId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'totalEvents', type: 'integer', example: 5),
                        new OA\Property(
                            property: 'history',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(
                                        property: 'event',
                                        properties: [
                                            new OA\Property(property: 'type', type: 'string', example: 'GiftCardCreated'),
                                            new OA\Property(property: 'number', type: 'integer', example: 1),
                                            new OA\Property(property: 'occurredAt', type: 'string', format: 'date-time'),
                                            new OA\Property(property: 'payload', description: 'Event-specific data', type: 'object')
                                        ],
                                        type: 'object'
                                    ),
                                    new OA\Property(
                                        property: 'stateAfterEvent',
                                        properties: [
                                            new OA\Property(property: 'status', type: 'string', example: 'INACTIVE'),
                                            new OA\Property(
                                                property: 'balance',
                                                properties: [
                                                    new OA\Property(property: 'amount', type: 'integer'),
                                                    new OA\Property(property: 'currency', type: 'string'),
                                                    new OA\Property(property: 'formatted', type: 'string')
                                                ],
                                                type: 'object'
                                            ),
                                            new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', nullable: true),
                                            new OA\Property(property: 'activatedAt', type: 'string', format: 'date-time', nullable: true),
                                            new OA\Property(property: 'suspendedAt', type: 'string', format: 'date-time', nullable: true),
                                            new OA\Property(property: 'cancelledAt', type: 'string', format: 'date-time', nullable: true),
                                            new OA\Property(property: 'expiredAt', type: 'string', format: 'date-time', nullable: true),
                                            new OA\Property(property: 'depletedAt', type: 'string', format: 'date-time', nullable: true),
                                            new OA\Property(property: 'suspensionDuration', type: 'integer')
                                        ],
                                        type: 'object'
                                    )
                                ],
                                type: 'object'
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'HMAC authentication failed'),
            new OA\Response(response: 404, description: 'Gift Card not found'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function getHistory(string $id): JsonResponse
    {
        try {
            $this->assertValidUuid($id);
            $query = new GetGiftCardHistoryQuery($id);

            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);
            $historyView = $handledStamp?->getResult();

            if (!$historyView) {
                return new JsonResponse([
                    'error' => 'Gift Card not found'
                ], Response::HTTP_NOT_FOUND);
            }

            return new JsonResponse($historyView->toArray());

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }
}
