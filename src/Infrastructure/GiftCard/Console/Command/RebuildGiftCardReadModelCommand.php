<?php

declare(strict_types=1);

namespace App\Infrastructure\GiftCard\Console\Command;

use App\Domain\GiftCard\Event\GiftCardActivated;
use App\Domain\GiftCard\Event\GiftCardBalanceAdjusted;
use App\Domain\GiftCard\Event\GiftCardBalanceDecreased;
use App\Domain\GiftCard\Event\GiftCardCancelled;
use App\Domain\GiftCard\Event\GiftCardCreated;
use App\Domain\GiftCard\Event\GiftCardDepleted;
use App\Domain\GiftCard\Event\GiftCardExpired;
use App\Domain\GiftCard\Event\GiftCardReactivated;
use App\Domain\GiftCard\Event\GiftCardRedeemed;
use App\Domain\GiftCard\Event\GiftCardSuspended;
use App\Infrastructure\GiftCard\Persistence\ReadModel\GiftCardReadModelProjection;
use Broadway\Serializer\Serializer;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:gift-card:rebuild-read-model',
    description: 'Rebuild gift card read model by replaying all domain events'
)]
final class RebuildGiftCardReadModelCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Serializer $serializer,
        private readonly GiftCardReadModelProjection $projection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('truncate', null, InputOption::VALUE_NONE, 'Truncate read model table before rebuild');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('truncate')) {
            $this->connection->executeStatement('TRUNCATE TABLE gift_cards_read');
            $output->writeln('<info>Read model table truncated.</info>');
        }

        $events = $this->connection->fetchAllAssociative('SELECT payload, type FROM events ORDER BY id ASC');

        foreach ($events as $eventRow) {
            $payload = json_decode($eventRow['payload'], true);
            if (!is_array($payload)) {
                continue;
            }

            $event = $this->serializer->deserialize($payload);
            $this->applyEvent($event);
        }

        $output->writeln('<info>Read model rebuild completed.</info>');

        return Command::SUCCESS;
    }

    private function applyEvent(object $event): void
    {
        match (true) {
            $event instanceof GiftCardCreated => $this->projection->onGiftCardCreated($event),
            $event instanceof GiftCardActivated => $this->projection->onGiftCardActivated($event),
            $event instanceof GiftCardRedeemed => $this->projection->onGiftCardRedeemed($event),
            $event instanceof GiftCardSuspended => $this->projection->onGiftCardSuspended($event),
            $event instanceof GiftCardReactivated => $this->projection->onGiftCardReactivated($event),
            $event instanceof GiftCardCancelled => $this->projection->onGiftCardCancelled($event),
            $event instanceof GiftCardExpired => $this->projection->onGiftCardExpired($event),
            $event instanceof GiftCardDepleted => $this->projection->onGiftCardDepleted($event),
            $event instanceof GiftCardBalanceAdjusted => $this->projection->onGiftCardBalanceAdjusted($event),
            $event instanceof GiftCardBalanceDecreased => $this->projection->onGiftCardBalanceDecreased($event),
            default => null,
        };
    }
}
