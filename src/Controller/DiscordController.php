<?php

declare(strict_types=1);

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DiscordController extends AbstractController
{
    #[Route('/connect/discord', name: 'connect_discord_start', methods: ['GET'])]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        // Redirect to Discord; we only need the user identity to check the whitelist.
        return $clientRegistry->getClient('discord')->redirect(['identify'], []);
    }

    /**
     * Discord redirects here after authorization. The request is intercepted by
     * DiscordAuthenticator, so this method is never actually executed.
     */
    #[Route('/connect/discord/check', name: 'connect_discord_check', methods: ['GET'])]
    public function check(): Response
    {
        throw new \LogicException('This route is handled by DiscordAuthenticator.');
    }
}
