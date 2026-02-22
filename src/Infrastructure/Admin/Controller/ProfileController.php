<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Controller;

use App\Application\User\Command\ChangePasswordCommand;
use App\Application\User\Command\UpdateProfileCommand;
use App\Application\User\Handler\ChangePassword;
use App\Application\User\Handler\UpdateProfile;
use App\Infrastructure\User\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly UpdateProfile $updateProfile,
        private readonly ChangePassword $changePassword,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Route('/admin/profile', name: 'admin_profile', methods: ['GET'])]
    public function index(): Response
    {
        /** @var SecurityUser $securityUser */
        $securityUser = $this->getUser();
        $user = $securityUser->getDomainUser();

        return $this->render('admin/profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/admin/profile/personal', name: 'admin_profile_personal', methods: ['POST'])]
    public function updatePersonal(Request $request): Response
    {
        /** @var SecurityUser $securityUser */
        $securityUser = $this->getUser();
        $user = $securityUser->getDomainUser();

        if (!$this->isCsrfTokenValid('profile_personal', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $firstName = $request->request->getString('first_name') ?: null;
        $lastName = $request->request->getString('last_name') ?: null;

        try {
            ($this->updateProfile)(new UpdateProfileCommand(
                $user->getId()->toString(),
                $firstName,
                $lastName,
            ));

            $this->addFlash('success', 'profile.personal_updated');
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_profile');
    }

    #[Route('/admin/profile/password', name: 'admin_profile_password', methods: ['POST'])]
    public function changePasswordAction(Request $request): Response
    {
        /** @var SecurityUser $securityUser */
        $securityUser = $this->getUser();
        $user = $securityUser->getDomainUser();

        if (!$this->isCsrfTokenValid('profile_password', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $currentPassword = $request->request->getString('current_password');
        $newPassword = $request->request->getString('new_password');
        $newPasswordConfirm = $request->request->getString('new_password_confirm');

        if (!$this->passwordHasher->isPasswordValid($securityUser, $currentPassword)) {
            $this->addFlash('danger', 'profile.current_password_invalid');

            return $this->redirectToRoute('admin_profile');
        }

        if ($newPassword !== $newPasswordConfirm) {
            $this->addFlash('danger', 'profile.passwords_do_not_match');

            return $this->redirectToRoute('admin_profile');
        }

        if (strlen($newPassword) < 8) {
            $this->addFlash('danger', 'profile.password_too_short');

            return $this->redirectToRoute('admin_profile');
        }

        try {
            ($this->changePassword)(new ChangePasswordCommand(
                $user->getId()->toString(),
                $newPassword,
            ));

            $this->addFlash('success', 'profile.password_changed');
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_profile');
    }
}
