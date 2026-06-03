<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Notification;
use App\Service\NtfyPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notify:test',
    description: 'Sends an immediate fake-walk notification to ntfy (with the 3 quick-reply buttons).',
)]
final class NotifyTestCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NtfyPublisher $publisher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $notification = new Notification(new \DateTimeImmutable());
        $this->em->persist($notification);
        $this->em->flush();

        try {
            $this->publisher->publishFakeWalk($notification);
            $notification->markSent();
            $this->em->flush();
        } catch (\Throwable $e) {
            $notification->markFailed();
            $this->em->flush();
            $io->error(sprintf('ntfy publish failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf('Test notification #%d sent to ntfy. Tap a button on your phone, then check the backoffice.', $notification->getId()));

        return Command::SUCCESS;
    }
}
