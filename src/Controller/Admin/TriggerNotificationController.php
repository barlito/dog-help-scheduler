<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Notification;
use App\Repository\NotificationTypeRepository;
use App\Service\NtfyMessageFactory;
use App\Service\NtfyPublisher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sends one immediate notification (of the first enabled type), triggered from the
 * dashboard button. Useful to test the ntfy + buttons flow end to end.
 */
final class TriggerNotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationTypeRepository $types,
        private readonly NtfyMessageFactory $messageFactory,
        private readonly NtfyPublisher $publisher,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/admin/trigger-notification', name: 'admin_trigger_notification', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('trigger_notification', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('admin');
        }

        $type = $this->types->findEnabled()[0] ?? null;
        if (null === $type) {
            $this->addFlash('warning', 'Aucun type de notification activé. Crée-en un d\'abord.');

            return $this->redirectToRoute('admin');
        }

        $notification = new Notification($type, new \DateTimeImmutable());
        $this->em->persist($notification);
        $this->em->flush();

        try {
            $this->publisher->publish($this->messageFactory->forNotification($notification));
            $notification->markSent();
            $this->em->flush();
            $this->addFlash('success', \sprintf('Notification "%s" envoyée à ntfy.', $type->getLabel()));
        } catch (\Throwable $e) {
            $notification->markFailed();
            $this->em->flush();
            $this->logger->error('Manual trigger failed to publish notification #{id}: {error}', [
                'id' => (string) $notification->getId(),
                'error' => $e->getMessage(),
            ]);
            $this->addFlash('danger', 'Échec de l\'envoi à ntfy : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin');
    }
}
