<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class TriggerNotificationControllerTest extends WebTestCase
{
    public function testInvalidCsrfDoesNotSendAndRedirects(): void
    {
        $client = static::createClient();
        $provider = self::getContainer()->get('security.user.provider.concrete.admin_provider');
        \assert($provider instanceof UserProviderInterface);
        $client->loginUser($provider->loadUserByIdentifier('admin'), 'main');

        // No CSRF token → the controller bails out before publishing anything.
        $client->request('POST', '/admin/trigger-notification');

        self::assertResponseRedirects('/admin');
    }

    public function testRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/admin/trigger-notification');

        // Seamless entry point: unauthenticated access starts the Discord login.
        self::assertResponseRedirects();
        $this->assertStringContainsString('/connect/discord', $client->getResponse()->headers->get('Location') ?? '');
    }
}
