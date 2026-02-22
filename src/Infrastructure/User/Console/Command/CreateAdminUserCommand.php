<?php

declare(strict_types=1);

namespace App\Infrastructure\User\Console\Command;

use App\Domain\User\Entity\User;
use App\Domain\User\Port\UserRepository;
use App\Domain\User\ValueObject\UserEmail;
use App\Domain\User\ValueObject\UserId;
use App\Domain\User\ValueObject\UserRole;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an admin user for the backoffice panel'
)]
final class CreateAdminUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin email', 'admin@giftcard.pl')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Admin password', 'admin123')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'User role (OWNER, ADMIN, SUPPORT)', 'OWNER');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = UserEmail::fromString($input->getOption('email'));
        $role = UserRole::fromString($input->getOption('role'));
        $password = $input->getOption('password');

        if ($this->userRepository->existsByEmail($email)) {
            $output->writeln(sprintf('<comment>User %s already exists, skipping.</comment>', $email->toString()));
            return Command::SUCCESS;
        }

        $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);
        $hash = $hasher->hash($password);

        $user = User::register(
            UserId::generate(),
            $email,
            $hash,
            $role
        );

        $this->userRepository->save($user);

        $output->writeln(sprintf('<info>Admin user created: %s (%s)</info>', $email->toString(), $role->toString()));

        return Command::SUCCESS;
    }
}
