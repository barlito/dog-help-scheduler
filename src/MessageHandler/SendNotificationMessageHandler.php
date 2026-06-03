<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendNotificationMessage;
use App\Repository\NotificationRepository;
use App\Service\NtfyPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class SendNotificationMessageHandler
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly EntityManagerInterface $em,
        private readonly NtfyPublisher $publisher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendNotificationMessage $message): void
    {
        $notification = $this->notifications->find(Uuid::fromString($message->notificationId));
        if (null === $notification) {
            $this->logger->warning('SendNotification skipped: #{id} no longer exists.', ['id' => $message->notificationId]);

            return;
        }

        // Guard against double delivery / replays: only send while still planned.
        if (!$notification->getStatus()->isAnswered() && null !== $notification->getSentAt()) {
            return;
        }

        try {
            $this->publisher->publishFakeWalk($notification);
            $notification->markSent();
            $this->em->flush();
        } catch (\Throwable $e) {
            $notification->markFailed();
            $this->em->flush();
            $this->logger->error('Failed to publish notification #{id}: {error}', [
                'id' => $notification->getId(),
                'error' => $e->getMessage(),
            ]);

            // Rethrow so Messenger retries and, once exhausted, routes it to the failed transport.
            throw $e;
        }
    }
}
