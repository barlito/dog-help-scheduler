<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Notification;
use App\Repository\NotificationTypeRepository;
use App\Service\NtfyMessageFactory;
use App\Service\NtfyPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notify:test',
    description: 'Sends an immediate notification to ntfy (with the 3 quick-reply buttons).',
)]
final class NotifyTestCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationTypeRepository $types,
        private readonly NtfyMessageFactory $messageFactory,
        private readonly NtfyPublisher $publisher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $type = $this->types->findEnabled()[0] ?? null;
        if (null === $type) {
            $io->error('No enabled notification type found. Create one in the backoffice first.');

            return Command::FAILURE;
        }

        $notification = new Notification($type, new \DateTimeImmutable());
        $this->em->persist($notification);
        $this->em->flush();

        try {
            $this->publisher->publish($this->messageFactory->forNotification($notification));
            $notification->markSent();
            $this->em->flush();
        } catch (\Throwable $e) {
            $notification->markFailed();
            $this->em->flush();
            $io->error(\sprintf('ntfy publish failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Test notification "%s" #%s sent to ntfy. Tap a button on your phone, then check the backoffice.', $type->getLabel(), $notification->getId()));

        return Command::SUCCESS;
    }
}
