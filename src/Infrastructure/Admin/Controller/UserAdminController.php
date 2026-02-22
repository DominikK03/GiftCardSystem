<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Controller;

use App\Application\User\Command\ActivateUserCommand;
use App\Application\User\Command\ChangePasswordCommand;
use App\Application\User\Command\ChangeUserRoleCommand;
use App\Application\User\Command\DeactivateUserCommand;
use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\Handler\ActivateUser;
use App\Application\User\Handler\ChangePassword;
use App\Application\User\Handler\ChangeUserRole;
use App\Application\User\Handler\DeactivateUser;
use App\Application\User\Handler\RegisterUser;
use App\Domain\User\Port\UserRepository;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use App\Infrastructure\User\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserAdminController extends AbstractController
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RegisterUser $registerUser,
        private readonly ChangeUserRole $changeUserRole,
        private readonly ChangePassword $changePassword,
        private readonly ActivateUser $activateUser,
        private readonly DeactivateUser $deactivateUser,
    ) {}

    #[Route('/admin/users', name: 'admin_user_index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $roleFilter = $request->query->getString('role') ?: null;
        $statusFilter = $request->query->getString('status') ?: null;

        $users = $this->userRepository->findAll($page, self::PER_PAGE);
        $total = $this->userRepository->count();

        if ($roleFilter !== null) {
            $users = array_filter($users, fn ($u) => $u->getRole()->value === $roleFilter);
        }

        if ($statusFilter === 'active') {
            $users = array_filter($users, fn ($u) => $u->isActive());
        } elseif ($statusFilter === 'inactive') {
            $users = array_filter($users, fn ($u) => !$u->isActive());
        }

        $filteredTotal = count($users);
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'total' => $filteredTotal,
            'currentRole' => $roleFilter ?? '',
            'currentStatus' => $statusFilter ?? '',
            'roles' => UserRole::cases(),
        ]);
    }

    #[Route('/admin/users/create', name: 'admin_user_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_OWNER');

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('user_create', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $email = $request->request->getString('email');
            $password = $request->request->getString('password');
            $role = $request->request->getString('role');

            try {
                ($this->registerUser)(new RegisterUserCommand($email, $password, $role));

                $this->addFlash('success', 'user.created_success');

                return $this->redirectToRoute('admin_user_index');
            } catch (\Throwable $e) {
                return $this->render('admin/user/create.html.twig', [
                    'errors' => [$e->getMessage()],
                    'formData' => ['email' => $email, 'role' => $role],
                    'roles' => UserRole::cases(),
                ]);
            }
        }

        return $this->render('admin/user/create.html.twig', [
            'roles' => UserRole::cases(),
        ]);
    }

    #[Route('/admin/users/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_OWNER');

        $user = $this->userRepository->findById(UserId::fromString($id));
        if ($user === null) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('user_edit', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $newRole = $request->request->getString('role');
            $newPassword = $request->request->getString('password');

            try {
                if ($newRole !== $user->getRole()->value) {
                    ($this->changeUserRole)(new ChangeUserRoleCommand($id, $newRole));
                }

                if ($newPassword !== '') {
                    ($this->changePassword)(new ChangePasswordCommand($id, $newPassword));
                }

                $this->addFlash('success', 'user.updated_success');

                return $this->redirectToRoute('admin_user_index');
            } catch (\Throwable $e) {
                return $this->render('admin/user/edit.html.twig', [
                    'user' => $user,
                    'errors' => [$e->getMessage()],
                    'roles' => UserRole::cases(),
                ]);
            }
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    #[Route('/admin/users/{id}/toggle-active', name: 'admin_user_toggle_active', methods: ['POST'])]
    public function toggleActive(string $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_OWNER');

        if (!$this->isCsrfTokenValid('user_toggle_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->userRepository->findById(UserId::fromString($id));
        if ($user === null) {
            throw $this->createNotFoundException();
        }

        try {
            if ($user->isActive()) {
                ($this->deactivateUser)(new DeactivateUserCommand($id));
                $this->addFlash('success', 'user.deactivated_success');
            } else {
                ($this->activateUser)(new ActivateUserCommand($id));
                $this->addFlash('success', 'user.activated_success');
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/admin/users/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_OWNER');

        if (!$this->isCsrfTokenValid('user_delete_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        /** @var SecurityUser $securityUser */
        $securityUser = $this->getUser();
        $currentUserId = $securityUser->getDomainUser()->getId()->toString();

        if ($currentUserId === $id) {
            $this->addFlash('danger', 'user.cannot_delete_self');

            return $this->redirectToRoute('admin_user_index');
        }

        $user = $this->userRepository->findById(UserId::fromString($id));
        if ($user === null) {
            throw $this->createNotFoundException();
        }

        $this->userRepository->delete($user);
        $this->addFlash('success', 'user.deleted_success');

        return $this->redirectToRoute('admin_user_index');
    }
}
