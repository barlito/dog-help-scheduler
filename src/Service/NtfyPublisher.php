<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SettingsRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin transport that publishes a prepared {@see NtfyMessage} to ntfy.
 *
 * The server base URL is configured on the scoped `ntfy.client` HTTP client
 * (see config/packages/framework.yaml); the topic comes from the editable Settings
 * (falling back to the NTFY_TOPIC env var).
 *
 * @see https://docs.ntfy.sh/publish/#publish-as-json
 */
final class NtfyPublisher
{
    public function __construct(
        private readonly HttpClientInterface $ntfyClient,
        private readonly SettingsRepository $settings,
        #[Autowire('%env(NTFY_TOPIC)%')]
        private readonly string $defaultTopic,
    ) {
    }

    public function publish(NtfyMessage $message): void
    {
        $topic = $this->settings->get()?->getNtfyTopic() ?: $this->defaultTopic;

        $payload = [
            'topic' => $topic,
            'title' => $message->title,
            'message' => $message->message,
            'tags' => $message->tags,
            'actions' => $message->actions,
        ];
        if (null !== $message->icon) {
            $payload['icon'] = $message->icon;
        }

        // base_uri of the scoped client points at the ntfy server (JSON publish endpoint).
        $response = $this->ntfyClient->request('POST', '', ['json' => $payload]);

        // Force the request to resolve and surface transport/HTTP errors to the caller.
        $response->getStatusCode();
        $response->getContent();
    }
}
