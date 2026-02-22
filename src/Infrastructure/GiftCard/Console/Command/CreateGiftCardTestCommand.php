<?php

declare(strict_types=1);

namespace App\Infrastructure\GiftCard\Console\Command;

use App\Application\GiftCard\Command\CreateCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsCommand(
    name: 'app:gift-card:create-test',
    description: 'Creates a test Gift Card to verify Event Sourcing and RabbitMQ flow'
)]
final class CreateGiftCardTestCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('amount', InputArgument::OPTIONAL, 'Amount in grosze (default: 10000 = 100.00 PLN)', '10000')
            ->addArgument('currency', InputArgument::OPTIONAL, 'Currency code (default: PLN)', 'PLN');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $amount = (int) $input->getArgument('amount');
        $currency = (string) $input->getArgument('currency');

        $io->title('Creating Test Gift Card');
        $io->text([
            sprintf('Amount: %d %s (%.2f %s)', $amount, $currency, $amount / 100, $currency),
            'This will test the complete flow:',
            '1. Command → Handler',
            '2. Aggregate → Events',
            '3. Events → Event Store (PostgreSQL)',
            '4. Events → Messenger → RabbitMQ',
        ]);

        try {
            $command = new CreateCommand(
                amount: $amount,
                currency: $currency
            );

            $envelope = $this->messageBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);
            $id = $handledStamp?->getResult();

            $io->success([
                sprintf('Gift Card created successfully! ID: %s', $id),
                '',
                'Next steps to verify:',
                '1. Check Event Store: SELECT * FROM events ORDER BY recorded_on DESC LIMIT 5;',
                '2. Check RabbitMQ Management UI: http://localhost:15672',
                '3. Check gift_card_events queue for GiftCardCreated event',
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error([
                'Failed to create Gift Card',
                sprintf('Error: %s', $e->getMessage()),
                sprintf('Class: %s', get_class($e)),
            ]);

            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
