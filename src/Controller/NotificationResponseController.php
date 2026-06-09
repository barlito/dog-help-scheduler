<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Notification;
use App\Enum\NotificationStatus;
use App\Message\SendNotificationMessage;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public endpoint hit by the ntfy "http" action buttons on the phone.
 * Authorisation relies on the per-notification random token in the URL.
 */
final class NotificationResponseController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly NotificationRepository $notifications,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(
        '/n/{id}/{token}/{action}',
        name: 'notification_response',
        requirements: ['id' => '[0-9a-fA-F-]{36}', 'action' => 'validated|postponed|not_done'],
        methods: ['GET', 'POST'],
    )]
    public function __invoke(Notification $notification, string $token, string $action): Response
    {
        if (!hash_equals($notification->getResponseToken(), $token)) {
            $this->logger->warning('Notification response rejected: bad token for #{id}.', [
                'id' => (string) $notification->getId(),
            ]);

            return new Response('Lien invalide.', Response::HTTP_NOT_FOUND);
        }

        $status = NotificationStatus::from($action);

        try {
            $recorded = $notification->recordResponse($status);
        } catch (\InvalidArgumentException) {
            // Defensive: the route already restricts {action} to answerable statuses,
            // but guard against future drift between the route and the enum.
            return new Response('Action invalide.', Response::HTTP_BAD_REQUEST);
        }

        if (!$recorded) {
            return $this->text(\sprintf('Déjà répondu (%s). 🐾', $notification->getStatus()->label()));
        }

        // "Reporter" re-sends the same notification later (status goes back to SENT when
        // it fires). The re-fire time is staggered so that a burst of postpones — e.g.
        // catching up on a late morning — does not repop all at once. Other answers are final.
        $now = new \DateTimeImmutable();
        $postponedUntil = null;
        if (NotificationStatus::POSTPONED === $status) {
            $postponedUntil = $this->staggeredPostponeTime($notification, $now);
            $notification->setPostponedUntil($postponedUntil);
        }

        $this->em->flush();

        $this->logger->info('Notification #{id} answered: {action}.', [
            'id' => (string) $notification->getId(),
            'action' => $status->value,
        ]);

        if (null !== $postponedUntil) {
            $delaySeconds = $postponedUntil->getTimestamp() - $now->getTimestamp();
            $this->bus->dispatch(
                new SendNotificationMessage((string) $notification->getId()),
                [new DelayStamp($delaySeconds * 1000)],
            );

            return $this->text(\sprintf('Reporté de %d min. 🐾', intdiv($delaySeconds, 60)));
        }

        return $this->text(\sprintf('Réponse enregistrée : %s. Merci ! 🐾', $status->label()));
    }

    /**
     * The type's postpone delay from now, pushed back behind the latest postponed
     * notification still waiting to repop (plus the same delay as spacing), so that
     * chained postpones come back one by one instead of together. A random jitter
     * (1 to the type's max, when configured) is then added so the repop never lands
     * exactly N minutes later.
     */
    private function staggeredPostponeTime(Notification $notification, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $type = $notification->getType();
        $minutes = $type->getPostponeMinutes();
        $until = $now->modify(\sprintf('+%d minutes', $minutes));

        $lastPending = $this->notifications->findLatestPendingPostponedUntil($now);
        if (null !== $lastPending) {
            $chained = $lastPending->modify(\sprintf('+%d minutes', $minutes));
            if ($chained > $until) {
                $until = $chained;
            }
        }

        $jitterMax = $type->getPostponeJitterMaxMinutes();
        if ($jitterMax > 0) {
            $until = $until->modify(\sprintf('+%d minutes', random_int(1, $jitterMax)));
        }

        return $until;
    }

    private function text(string $body): Response
    {
        return new Response($body, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
