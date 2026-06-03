<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Notification;
use App\Enum\NotificationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public endpoint hit by the ntfy "http" action buttons on the phone.
 * Authorisation relies on the per-notification random token in the URL.
 */
final class NotificationResponseController
{
    #[Route(
        '/n/{id}/{token}/{action}',
        name: 'notification_response',
        requirements: ['id' => '\d+', 'action' => 'validated|postponed|not_done'],
        methods: ['GET', 'POST'],
    )]
    public function __invoke(
        Notification $notification,
        string $token,
        string $action,
        EntityManagerInterface $em,
    ): Response {
        if (!hash_equals($notification->getResponseToken(), $token)) {
            return new Response('Lien invalide.', Response::HTTP_NOT_FOUND);
        }

        $status = NotificationStatus::from($action);

        $recorded = $notification->recordResponse($status);
        if ($recorded) {
            $em->flush();
        }

        $body = $recorded
            ? sprintf('Réponse enregistrée : %s. Merci ! 🐾', $status->label())
            : sprintf('Déjà répondu (%s). 🐾', $notification->getStatus()->label());

        return new Response($body, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
