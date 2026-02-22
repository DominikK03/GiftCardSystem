<?php

declare(strict_types=1);

namespace App\Interface\Http\Controller;

use App\Application\Tenant\Command\CancelTenantCommand;
use App\Application\Tenant\Command\CreateTenantCommand;
use App\Application\Tenant\Command\ReactivateTenantCommand;
use App\Application\Tenant\Command\RegenerateApiCredentialsCommand;
use App\Application\Tenant\Command\SuspendTenantCommand;
use App\Application\Tenant\Query\GetTenantQuery;
use App\Application\Tenant\Query\GetTenantsQuery;
use App\Interface\Http\Request\CreateTenantRequest;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Tenants')]
#[Route('/api/tenants', name: 'api_tenants_')]
final class TenantController extends AbstractController
{
    use JsonValidationTrait;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly ValidatorInterface $validator
    ) {}

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/tenants',
        summary: 'Create a new Tenant',
        requestBody: new OA\RequestBody(
            description: 'Tenant data',
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'nip', 'street', 'city', 'postalCode', 'country', 'phoneNumber', 'representativeFirstName', 'representativeLastName'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Test Company Ltd.'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'contact@testcompany.com'),
                    new OA\Property(property: 'nip', type: 'string', example: '1234567890'),
                    new OA\Property(property: 'street', type: 'string', example: 'ul. Testowa 123'),
                    new OA\Property(property: 'city', type: 'string', example: 'Warszawa'),
                    new OA\Property(property: 'postalCode', type: 'string', example: '00-001'),
                    new OA\Property(property: 'country', type: 'string', example: 'Polska'),
                    new OA\Property(property: 'phoneNumber', type: 'string', example: '+48123456789'),
                    new OA\Property(property: 'representativeFirstName', type: 'string', example: 'Jan'),
                    new OA\Property(property: 'representativeLastName', type: 'string', example: 'Kowalski')
                ]
            )
        ),
        tags: ['Tenants'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Tenant created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Tenant created successfully'),
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', description: 'Generated Tenant UUID'),
                        new OA\Property(property: 'apiKey', type: 'string', description: 'Generated API Key (32 chars)'),
                        new OA\Property(property: 'apiSecret', type: 'string', description: 'Generated API Secret (64 chars)')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = $this->validateAndDecodeJson($request);
            $dto = CreateTenantRequest::fromArray($data);
            $this->validateDto($dto, $this->validator);

            $command = new CreateTenantCommand(
                name: (string) $dto->name,
                email: (string) $dto->email,
                nip: (string) $dto->nip,
                street: (string) $dto->street,
                city: (string) $dto->city,
                postalCode: (string) $dto->postalCode,
                country: (string) $dto->country,
                phoneNumber: (string) $dto->phoneNumber,
                representativeFirstName: (string) $dto->representativeFirstName,
                representativeLastName: (string) $dto->representativeLastName
            );

            $envelope = $this->messageBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);
            $tenantId = $handledStamp?->getResult();

            return new JsonResponse([
                'message' => 'Tenant created successfully',
                'id' => $tenantId
            ], Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/{id}/suspend', name: 'suspend', methods: ['POST'])]
    #[OA\Post(
        path: '/api/tenants/{id}/suspend',
        summary: 'Suspend a Tenant',
        tags: ['Tenants'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Tenant UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tenant suspended successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Tenant suspended successfully')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 404, description: 'Tenant not found'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function suspend(string $id): JsonResponse
    {
        try {
            $this->assertValidUuid($id);

            $command = new SuspendTenantCommand(tenantId: $id);
            $this->messageBus->dispatch($command);

            return new JsonResponse([
                'message' => 'Tenant suspended successfully'
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/{id}/reactivate', name: 'reactivate', methods: ['POST'])]
    #[OA\Post(
        path: '/api/tenants/{id}/reactivate',
        summary: 'Reactivate a suspended Tenant',
        tags: ['Tenants'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Tenant UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tenant reactivated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Tenant reactivated successfully')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 404, description: 'Tenant not found'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function reactivate(string $id): JsonResponse
    {
        try {
            $this->assertValidUuid($id);

            $command = new ReactivateTenantCommand(tenantId: $id);
            $this->messageBus->dispatch($command);

            return new JsonResponse([
                'message' => 'Tenant reactivated successfully'
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    #[OA\Post(
        path: '/api/tenants/{id}/cancel',
        summary: 'Cancel a Tenant',
        tags: ['Tenants'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Tenant UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tenant cancelled successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Tenant cancelled successfully')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 404, description: 'Tenant not found'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function cancel(string $id): JsonResponse
    {
        try {
            $this->assertValidUuid($id);

            $command = new CancelTenantCommand(tenantId: $id);
            $this->messageBus->dispatch($command);

            return new JsonResponse([
                'message' => 'Tenant cancelled successfully'
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/{id}/regenerate-credentials', name: 'regenerate_credentials', methods: ['POST'])]
    #[OA\Post(
        path: '/api/tenants/{id}/regenerate-credentials',
        summary: 'Regenerate API credentials for a Tenant',
        tags: ['Tenants'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Tenant UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Credentials regenerated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Credentials regenerated successfully')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 404, description: 'Tenant not found'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function regenerateCredentials(string $id): JsonResponse
    {
        try {
            $this->assertValidUuid($id);

            $command = new RegenerateApiCredentialsCommand(tenantId: $id);
            $this->messageBus->dispatch($command);

            return new JsonResponse([
                'message' => 'Credentials regenerated successfully'
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/tenants/{id}',
        summary: 'Get Tenant by ID',
        tags: ['Tenants'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Tenant UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tenant details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'email', type: 'string'),
                        new OA\Property(property: 'nip', type: 'string'),
                        new OA\Property(
                            property: 'address',
                            properties: [
                                new OA\Property(property: 'street', type: 'string'),
                                new OA\Property(property: 'city', type: 'string'),
                                new OA\Property(property: 'postalCode', type: 'string'),
                                new OA\Property(property: 'country', type: 'string')
                            ],
                            type: 'object'
                        ),
                        new OA\Property(property: 'phoneNumber', type: 'string'),
                        new OA\Property(
                            property: 'representative',
                            properties: [
                                new OA\Property(property: 'firstName', type: 'string'),
                                new OA\Property(property: 'lastName', type: 'string')
                            ],
                            type: 'object'
                        ),
                        new OA\Property(property: 'apiKey', type: 'string'),
                        new OA\Property(property: 'status', type: 'string'),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'suspendedAt', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'cancelledAt', type: 'string', format: 'date-time', nullable: true)
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 404, description: 'Tenant not found'),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function get(string $id): JsonResponse
    {
        try {
            $this->assertValidUuid($id);

            $query = new GetTenantQuery(id: $id);
            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(\Symfony\Component\Messenger\Stamp\HandledStamp::class);
            $tenantView = $handledStamp?->getResult();

            return new JsonResponse($tenantView->toArray(), Response::HTTP_OK);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/tenants',
        summary: 'Get list of Tenants with pagination',
        tags: ['Tenants'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Page number (default: 1)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                description: 'Items per page (default: 20)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of tenants',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'tenants', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'total', type: 'integer', description: 'Total number of tenants'),
                        new OA\Property(property: 'page', type: 'integer', description: 'Current page'),
                        new OA\Property(property: 'limit', type: 'integer', description: 'Items per page'),
                        new OA\Property(property: 'totalPages', type: 'integer', description: 'Total pages')
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'Internal server error')
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        try {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

            $query = new GetTenantsQuery(page: $page, limit: $limit);
            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(\Symfony\Component\Messenger\Stamp\HandledStamp::class);
            $result = $handledStamp?->getResult();

            $result['tenants'] = array_map(
                fn($view) => $view->toArray(),
                $result['tenants']
            );

            return new JsonResponse($result, Response::HTTP_OK);

        } catch (\Throwable $e) {
            return $this->handleDomainException($e);
        }
    }
}
