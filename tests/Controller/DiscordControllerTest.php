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
}
