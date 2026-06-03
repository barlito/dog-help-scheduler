<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin transport that publishes a prepared {@see NtfyMessage} to ntfy.
 *
 * The server base URL is configured on the scoped `ntfy.client` HTTP client
 * (see config/packages/framework.yaml); this class only knows how to talk to ntfy.
 *
 * @see https://docs.ntfy.sh/publish/#publish-as-json
 */
final class NtfyPublisher
{
    public function __construct(
        private readonly HttpClientInterface $ntfyClient,
        #[Autowire('%env(NTFY_TOPIC)%')]
        private readonly string $ntfyTopic,
    ) {
    }

    public function publish(NtfyMessage $message): void
    {
        $payload = [
            'topic' => $this->ntfyTopic,
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
