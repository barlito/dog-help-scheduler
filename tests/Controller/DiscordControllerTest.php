<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DiscordControllerTest extends WebTestCase
{
    public function testStartRouteRedirectsToDiscord(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connect/discord');

        self::assertResponseRedirects();
        $this->assertStringContainsString(
            'discord.com',
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
