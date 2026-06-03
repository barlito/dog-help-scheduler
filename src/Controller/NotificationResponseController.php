<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Notification;
use App\Enum\NotificationStatus;
use App\Message\SendNotificationMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
        #[Autowire('%env(int:NOTIF_POSTPONE_MINUTES)%')]
        private readonly int $postponeMinutes,
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

        $this->em->flush();

        // "Reporter" re-sends the same notification after a delay (status will go back
        // to SENT when it fires). Other answers are final.
        if (NotificationStatus::POSTPONED === $status) {
            $this->bus->dispatch(
                new SendNotificationMessage((string) $notification->getId()),
                [new DelayStamp($this->postponeMinutes * 60 * 1000)],
            );

            return $this->text(\sprintf('Reporté de %d min. 🐾', $this->postponeMinutes));
        }

        return $this->text(\sprintf('Réponse enregistrée : %s. Merci ! 🐾', $status->label()));
    }

    private function text(string $body): Response
    {
        return new Response($body, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
