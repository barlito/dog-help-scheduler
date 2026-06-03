<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Enum\NotificationStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Publishes notifications to ntfy with three "http" quick-reply buttons.
 *
 * @see https://docs.ntfy.sh/publish/#action-buttons
 */
final class NtfyPublisher
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(NTFY_SERVER)%')]
        private readonly string $ntfyServer,
        #[Autowire('%env(NTFY_TOPIC)%')]
        private readonly string $ntfyTopic,
        #[Autowire('%env(APP_PUBLIC_URL)%')]
        private readonly string $publicUrl,
    ) {
    }

    public function publishFakeWalk(Notification $notification): void
    {
        $this->publish(
            title: '🐕 Fausse sortie',
            message: "C'est l'heure d'une fausse sortie pour aider loulou à rester calme seul. Tu l'as faite ?",
            tags: ['dog2', 'walking'],
            actions: [
                $this->httpAction('✅ Validé', $notification, NotificationStatus::VALIDATED),
                $this->httpAction('🔁 Reporter', $notification, NotificationStatus::POSTPONED),
                $this->httpAction('❌ Non effectué', $notification, NotificationStatus::NOT_DONE),
            ],
        );
    }

    /**
     * Low-level publish used by the fake-walk message and the debug command.
     *
     * @param string[]                         $tags
     * @param array<int, array<string, mixed>> $actions
     */
    public function publish(string $title, string $message, array $tags = [], array $actions = []): void
    {
        $response = $this->httpClient->request('POST', rtrim($this->ntfyServer, '/'), [
            'json' => [
                'topic' => $this->ntfyTopic,
                'title' => $title,
                'message' => $message,
                'tags' => $tags,
                'actions' => $actions,
            ],
        ]);

        // Force the request to resolve and surface transport/HTTP errors to the caller.
        $response->getStatusCode();
        $response->getContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function httpAction(string $label, Notification $notification, NotificationStatus $status): array
    {
        return [
            'action' => 'http',
            'label' => $label,
            'url' => $this->callbackUrl($notification, $status),
            'method' => 'POST',
            // Dismiss the notification from the phone once the button is tapped.
            'clear' => true,
        ];
    }

    private function callbackUrl(Notification $notification, NotificationStatus $status): string
    {
        return \sprintf(
            '%s/n/%s/%s/%s',
            rtrim($this->publicUrl, '/'),
            $notification->getId(),
            $notification->getResponseToken(),
            $status->value,
        );
    }
}
