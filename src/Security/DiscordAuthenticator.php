<?php

declare(strict_types=1);

namespace App\Security;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Logs the user in via Discord OAuth, restricting access to a single whitelisted
 * Discord account which maps to the in-memory "admin" user (ROLE_ADMIN). Also the
 * firewall entry point: unauthenticated users land on the login page.
 */
final class DiscordAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    /** Set by this authenticator to make the next connect attempt skip prompt=none. */
    public const string FORCE_INTERACTIVE = 'discord_force_interactive';

    /** Guards against looping: at most one interactive retry per login attempt. */
    private const string INTERACTIVE_TRIED = 'discord_interactive_tried';

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(DISCORD_ALLOWED_USER_ID)%')]
        private readonly string $allowedUserId,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'connect_discord_check' === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('discord');
        $accessToken = $this->fetchAccessToken($client);

        $discordUser = $client->fetchUserFromToken($accessToken);
        $discordId = (string) $discordUser->getId();

        if ('' === $this->allowedUserId || $discordId !== $this->allowedUserId) {
            throw new CustomUserMessageAuthenticationException('Ce compte Discord n\'est pas autorisé.');
        }

        // The whitelisted Discord account maps to the single in-memory admin.
        // The RememberMeBadge lets the firewall issue a remember-me cookie
        // (always_remember_me is on, so no checkbox is needed).
        return new SelfValidatingPassport(
            new UserBadge('admin'),
            [new RememberMeBadge()],
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $request->getSession()->remove(self::INTERACTIVE_TRIED);

        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);

        return new RedirectResponse($targetPath ?? $this->urlGenerator->generate('admin'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $session = $request->getSession();

        // A silent (prompt=none) attempt comes back with an ?error and no code when
        // the user isn't logged in to Discord / hasn't authorized the app yet. Retry
        // once with the full interactive consent screen before giving up.
        if ($request->query->has('error') && !$session->get(self::INTERACTIVE_TRIED, false)) {
            $session->set(self::INTERACTIVE_TRIED, true);
            $session->set(self::FORCE_INTERACTIVE, true);

            return new RedirectResponse($this->urlGenerator->generate('connect_discord_start'));
        }

        $session->remove(self::INTERACTIVE_TRIED);
        $session->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->urlGenerator->generate('login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // Seamless: start a silent Discord login instead of showing the login page.
        return new RedirectResponse($this->urlGenerator->generate('connect_discord_start'));
    }
}
