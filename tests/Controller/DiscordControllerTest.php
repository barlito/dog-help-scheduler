<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DiscordControllerTest extends WebTestCase
{
    public function testStartRouteRedirectsToDiscordSilently(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connect/discord');

        self::assertResponseRedirects();
        $location = $client->getResponse()->headers->get('Location') ?? '';
        $this->assertStringContainsString('discord.com', $location);
        // Silent login: skip the consent screen when already logged in to Discord.
        $this->assertStringContainsString('prompt=none', $location);
    }

    public function testProtectedAreaSeamlesslyStartsDiscordLogin(): void
    {
        $client = static::createClient();
        // Hitting a protected page unauthenticated must bounce straight to the Discord
        // connect route (seamless entry point), not to the login page.
        $client->request('GET', '/admin');

        self::assertResponseRedirects();
        $this->assertStringContainsString(
            '/connect/discord',
            $client->getResponse()->headers->get('Location') ?? '',
        );
    }

    public function testLoginPageOffersDiscordOnly(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        // The Discord button is present and the password field is gone.
        $this->assertGreaterThan(0, $crawler->filter('a[href$="/connect/discord"]')->count());
        $this->assertSame(0, $crawler->filter('input[type="password"]')->count());
    }
}
