<?php

declare(strict_types=1);

namespace App\Infrastructure\GiftCard\Console\Command;

use App\Application\GiftCard\Command\ExpireCommand;
use App\Application\GiftCard\Port\GiftCardReadModelRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:gift-card:expire-cards',
    description: 'Expires active gift cards that have passed their expiration date'
)]
final class ExpireGiftCardsCommand extends Command
{
    public function __construct(
        private readonly GiftCardReadModelRepositoryInterface $repository,
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only list cards to expire, do not dispatch commands');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $now = new \DateTimeImmutable();
        $expiring = $this->repository->findExpiring($now);

        $count = count($expiring);

        if ($count === 0) {
            $io->info('No gift cards to expire.');

            return Command::SUCCESS;
        }

        $io->text(sprintf('Found %d gift card(s) to expire.', $count));

        if ($dryRun) {
            $io->note('Dry run mode - no commands dispatched.');
            foreach ($expiring as $card) {
                $io->text(sprintf('  - %s (expires: %s)', $card->id, $card->expiresAt?->format('Y-m-d H:i:s')));
            }

            return Command::SUCCESS;
        }

        $dispatched = 0;

        try {
            foreach ($expiring as $card) {
                $this->messageBus->dispatch(
                    new ExpireCommand($card->id, $now->format('Y-m-d\TH:i:s.uP'))
                );
                $dispatched++;
            }
        } catch (\Throwable $e) {
            $io->error(sprintf('Failed after dispatching %d/%d commands: %s', $dispatched, $count, $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf('Dispatched %d expire command(s).', $dispatched));

        return Command::SUCCESS;
    }
}
