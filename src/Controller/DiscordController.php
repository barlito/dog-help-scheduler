<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\DiscordAuthenticator;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DiscordController extends AbstractController
{
    #[Route('/connect/discord', name: 'connect_discord_start', methods: ['GET'])]
    public function connect(Request $request, ClientRegistry $clientRegistry): RedirectResponse
    {
        // Silent login by default: prompt=none skips Discord's consent screen when the
        // user is already logged in and has authorized the app (seamless). If that
        // attempt failed, the authenticator set FORCE_INTERACTIVE to run the full flow.
        $forceInteractive = true === $request->getSession()->remove(DiscordAuthenticator::FORCE_INTERACTIVE);
        $options = $forceInteractive ? [] : ['prompt' => 'none'];

        // We only need the user identity to check the whitelist.
        return $clientRegistry->getClient('discord')->redirect(['identify'], $options);
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
