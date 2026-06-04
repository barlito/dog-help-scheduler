<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Enum\NotificationStatus;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the ntfy payload for a notification: the message content comes from the
 * notification's type config, while the quick-reply buttons are generic (same three
 * answers for every type, built from the notification id + token).
 */
final class NtfyMessageFactory
{
    public function __construct(
        #[Autowire('%env(APP_PUBLIC_URL)%')]
        private readonly string $publicUrl,
        #[Autowire('%env(NTFY_ICON_URL)%')]
        private readonly string $iconUrl = '',
    ) {
    }

    public function forNotification(Notification $notification): NtfyMessage
    {
        $type = $notification->getType();

        return new NtfyMessage(
            title: $type->getTitle(),
            message: $type->getMessage(),
            tags: $type->getTags(),
            actions: [
                $this->httpAction('✅ Validé', $notification, NotificationStatus::VALIDATED),
                $this->httpAction('🔁 Reporter', $notification, NotificationStatus::POSTPONED),
                $this->httpAction('❌ Non effectué', $notification, NotificationStatus::NOT_DONE),
            ],
            icon: '' !== $this->iconUrl ? $this->iconUrl : null,
        );
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
