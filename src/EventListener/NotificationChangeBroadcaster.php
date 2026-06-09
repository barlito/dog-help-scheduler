<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publishes a Mercure update whenever a flush touches a Notification, so the
 * open admin pages auto-refresh (see public/js/admin-live-refresh.js).
 *
 * The payload is a bare "something changed" ping carrying no data: the page
 * reloads through a normal authenticated request, which is what lets the hub
 * accept anonymous subscribers without leaking anything.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
final class NotificationChangeBroadcaster
{
    public const string TOPIC = 'notifications';

    private bool $notificationChanged = false;

    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->record($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->record($args->getObject());
    }

    /**
     * One publish per flush, after the transaction is done, however many
     * notifications changed at once (a day plan inserts a dozen in one go).
     */
    public function postFlush(): void
    {
        if (!$this->notificationChanged) {
            return;
        }

        $this->notificationChanged = false;

        try {
            // SSE events with an empty data buffer are never dispatched by
            // EventSource, hence the minimal non-empty payload.
            $this->hub->publish(new Update(self::TOPIC, '{}'));
        } catch (\Throwable $e) {
            // Live refresh is best-effort: an unreachable hub must never break
            // the actual write (ntfy callback, worker send, admin action).
            $this->logger->warning('Mercure publish failed: {error}', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }

    private function record(object $entity): void
    {
        if ($entity instanceof Notification) {
            $this->notificationChanged = true;
        }
    }
}
