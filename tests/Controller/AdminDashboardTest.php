<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class AdminDashboardTest extends WebTestCase
{
    public function testDashboardLoadsForAuthenticatedAdmin(): void
    {
        $client = static::createClient();
        // Load the real admin from the in-memory provider so the security listener's
        // user-refresh (which compares the password hash) keeps the session authenticated.
        $provider = self::getContainer()->get('security.user.provider.concrete.admin_provider');
        \assert($provider instanceof UserProviderInterface);
        $client->loginUser($provider->loadUserByIdentifier('admin'), 'main');

        $client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Taux de réussite');
    }

    public function testNotificationCrudListLoads(): void
    {
        $client = static::createClient();
        $provider = self::getContainer()->get('security.user.provider.concrete.admin_provider');
        \assert($provider instanceof UserProviderInterface);
        $client->loginUser($provider->loadUserByIdentifier('admin'), 'main');

        $crawler = $client->request('GET', '/admin');
        // Follow the "Notifications" menu entry to the CRUD index (exercises fields + filters).
        $client->click($crawler->selectLink('Notifications')->link());

        self::assertResponseIsSuccessful();
    }

    public function testNotificationTypeCrudListLoads(): void
    {
        $client = static::createClient();
        $provider = self::getContainer()->get('security.user.provider.concrete.admin_provider');
        \assert($provider instanceof UserProviderInterface);
        $client->loginUser($provider->loadUserByIdentifier('admin'), 'main');

        $crawler = $client->request('GET', '/admin');
        $client->click($crawler->selectLink('Types de notif')->link());

        self::assertResponseIsSuccessful();
    }

    public function testSettingsCrudLoads(): void
    {
        $client = static::createClient();
        $provider = self::getContainer()->get('security.user.provider.concrete.admin_provider');
        \assert($provider instanceof UserProviderInterface);
        $client->loginUser($provider->loadUserByIdentifier('admin'), 'main');

        $crawler = $client->request('GET', '/admin');
        $client->click($crawler->selectLink('Réglages')->link());

        self::assertResponseIsSuccessful();
    }

    public function testDashboardRedirectsAnonymousToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        self::assertResponseRedirects();
        $this->assertStringContainsString('/login', $client->getResponse()->headers->get('Location') ?? '');
    }
}
