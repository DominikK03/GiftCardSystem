<?php

declare(strict_types=1);

namespace App\Interface\Http\Controller;

use App\Application\User\Command\ActivateUserCommand;
use App\Application\User\Command\ChangePasswordCommand;
use App\Application\User\Command\ChangeUserRoleCommand;
use App\Application\User\Command\DeactivateUserCommand;
use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\Handler\ActivateUser;
use App\Application\User\Handler\ChangePassword;
use App\Application\User\Handler\ChangeUserRole;
use App\Application\User\Handler\DeactivateUser;
use App\Application\User\Handler\GetUser;
use App\Application\User\Handler\GetUsers;
use App\Application\User\Handler\RegisterUser;
use App\Application\User\Query\GetUserQuery;
use App\Application\User\Query\GetUsersQuery;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users', name: 'api_users_')]
#[OA\Tag(name: 'Users')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly RegisterUser $registerUser,
        private readonly GetUser $getUser,
        private readonly GetUsers $getUsers,
        private readonly ChangeUserRole $changeUserRole,
        private readonly DeactivateUser $deactivateUser,
        private readonly ActivateUser $activateUser,
        private readonly ChangePassword $changePassword
    ) {}

    #[Route('', name: 'register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users',
        summary: 'Register new backoffice user',
        description: 'Creates a new user account for backoffice access with specified role (OWNER/ADMIN/SUPPORT)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'role'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'secret123'),
                    new OA\Property(property: 'role', type: 'string', enum: ['OWNER', 'ADMIN', 'SUPPORT'], example: 'ADMIN')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User registered successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 500, description: 'User already exists', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $command = new RegisterUserCommand(
            email: $data['email'] ?? '',
            password: $data['password'] ?? '',
            role: $data['role'] ?? ''
        );

        $userId = ($this->registerUser)($command);

        return new JsonResponse(['id' => $userId], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users/{id}',
        summary: 'Get user details',
        description: 'Retrieves details of a specific backoffice user by ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'User UUID', schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
                        new OA\Property(property: 'role', type: 'string', enum: ['OWNER', 'ADMIN', 'SUPPORT'], example: 'ADMIN'),
                        new OA\Property(property: 'isActive', type: 'boolean', example: true),
                        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-01-15T10:30:00+00:00'),
                        new OA\Property(property: 'deactivatedAt', type: 'string', format: 'date-time', nullable: true, example: null)
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function get(string $id): JsonResponse
    {
        $query = new GetUserQuery(id: $id);
        $userView = ($this->getUser)($query);

        if ($userView === null) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($userView->toArray(), Response::HTTP_OK);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users',
        summary: 'List users with pagination',
        description: 'Retrieves a paginated list of all backoffice users',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number (default: 1)', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Items per page (default: 20, max: 100)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of users',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'users',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                                    new OA\Property(property: 'role', type: 'string', enum: ['OWNER', 'ADMIN', 'SUPPORT']),
                                    new OA\Property(property: 'isActive', type: 'boolean'),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'deactivatedAt', type: 'string', format: 'date-time', nullable: true)
                                ]
                            )
                        ),
                        new OA\Property(property: 'total', type: 'integer', example: 50),
                        new OA\Property(property: 'page', type: 'integer', example: 1),
                        new OA\Property(property: 'limit', type: 'integer', example: 20),
                        new OA\Property(property: 'totalPages', type: 'integer', example: 3)
                    ]
                )
            )
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $query = new GetUsersQuery(page: $page, limit: $limit);
        $result = ($this->getUsers)($query);

        return new JsonResponse($result, Response::HTTP_OK);
    }

    #[Route('/{id}/role', name: 'change_role', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/users/{id}/role',
        summary: 'Change user role',
        description: 'Updates the role of an existing backoffice user',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'User UUID', schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['role'],
                properties: [
                    new OA\Property(property: 'role', type: 'string', enum: ['OWNER', 'ADMIN', 'SUPPORT'], example: 'SUPPORT')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role changed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Role changed successfully')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function changeRole(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $command = new ChangeUserRoleCommand(
            userId: $id,
            newRole: $data['role'] ?? ''
        );

        ($this->changeUserRole)($command);

        return new JsonResponse(['message' => 'Role changed successfully'], Response::HTTP_OK);
    }

    #[Route('/{id}/deactivate', name: 'deactivate', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/users/{id}/deactivate',
        summary: 'Deactivate user',
        description: 'Deactivates a backoffice user account (sets isActive=false and deactivatedAt timestamp)',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'User UUID', schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User deactivated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'User deactivated successfully')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function deactivate(string $id): JsonResponse
    {
        $command = new DeactivateUserCommand(userId: $id);
        ($this->deactivateUser)($command);

        return new JsonResponse(['message' => 'User deactivated successfully'], Response::HTTP_OK);
    }

    #[Route('/{id}/activate', name: 'activate', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/users/{id}/activate',
        summary: 'Activate user',
        description: 'Activates a previously deactivated backoffice user account (sets isActive=true and clears deactivatedAt)',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'User UUID', schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User activated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'User activated successfully')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function activate(string $id): JsonResponse
    {
        $command = new ActivateUserCommand(userId: $id);
        ($this->activateUser)($command);

        return new JsonResponse(['message' => 'User activated successfully'], Response::HTTP_OK);
    }

    #[Route('/{id}/password', name: 'change_password', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/users/{id}/password',
        summary: 'Change user password',
        description: 'Updates the password for a backoffice user account (automatically hashed with bcrypt)',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'User UUID', schema: new OA\Schema(type: 'string', format: 'uuid'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'newsecret456')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password changed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Password changed successfully')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
            new OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))
        ]
    )]
    public function changePassword(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $command = new ChangePasswordCommand(
            userId: $id,
            newPassword: $data['password'] ?? ''
        );

        ($this->changePassword)($command);

        return new JsonResponse(['message' => 'Password changed successfully'], Response::HTTP_OK);
    }
}
