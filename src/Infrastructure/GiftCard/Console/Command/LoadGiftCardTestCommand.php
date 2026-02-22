<?php

declare(strict_types=1);

namespace App\Infrastructure\GiftCard\Console\Command;

use App\Domain\GiftCard\Port\GiftCardRepository;
use App\Domain\GiftCard\ValueObject\GiftCardId;
use App\Domain\GiftCard\ValueObject\Money;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:gift-card:load-test',
    description: 'Loads a Gift Card from Event Store to verify event replay'
)]
final class LoadGiftCardTestCommand extends Command
{
    public function __construct(
        private readonly GiftCardRepository $repository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Gift Card UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $id = GiftCardId::fromString((string) $input->getArgument('id'));

        try {
            $giftCard = $this->repository->load($id);

            if ($giftCard === null) {
                $io->error('Gift Card not found');
                return Command::FAILURE;
            }

            $io->success('Gift Card loaded successfully from Event Store!');

            $reflection = new \ReflectionClass($giftCard);

            $properties = [
                'id', 'balance', 'status', 'createdAt', 'expiresAt',
                'activatedAt', 'suspendedAt', 'cancelledAt', 'expiredAt', 'depletedAt'
            ];

            $io->table(
                ['Property', 'Value'],
                array_map(function ($propName) use ($giftCard, $reflection) {
                    try {
                        $prop = $reflection->getProperty($propName);
                        $prop->setAccessible(true);
                        $value = $prop->getValue($giftCard);

                        if ($value instanceof \DateTimeImmutable) {
                            return [$propName, $value->format('Y-m-d H:i:s')];
                        }

                        if ($value instanceof Money) {
                            return [$propName, sprintf('%d %s (%.2f %s)', $value->getAmount(), $value->getCurrency(), $value->getAmount() / 100, $value->getCurrency())];
                        }

                        if ($value === null) {
                            return [$propName, 'NULL'];
                        }

                        if (is_object($value)) {
                            if (method_exists($value, '__toString')) {
                                return [$propName, get_class($value) . ': ' . (string)$value];
                            }
                            return [$propName, get_class($value)];
                        }

                        return [$propName, (string)$value];
                    } catch (\ReflectionException $e) {
                        return [$propName, 'N/A'];
                    }
                }, $properties)
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error([
                'Failed to load Gift Card',
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
