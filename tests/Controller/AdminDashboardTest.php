<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\NotificationTypeCrudController;
use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Enum\NotificationStatus;
use App\EventListener\NotificationChangeBroadcaster;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class AdminDashboardTest extends WebTestCase
{
    /** Client authenticated as the backoffice admin. */
    private function createAdminClient(): KernelBrowser
    {
        $client = static::createClient();
        // Load the real admin from the in-memory provider so the security listener's
        // user-refresh (which compares the password hash) keeps the session authenticated.
        $provider = self::getContainer()->get('security.user.provider.concrete.admin_provider');
        \assert($provider instanceof UserProviderInterface);
        $client->loginUser($provider->loadUserByIdentifier('admin'), 'main');

        // Dashboard counts are global: start each test from an empty notification
        // table so they stay exact even though the test database persists between runs.
        self::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('DELETE FROM ' . Notification::class)
            ->execute()
        ;

        return $client;
    }

    public function testDashboardLoadsForAuthenticatedAdmin(): void
    {
        $client = $this->createAdminClient();

        $client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Taux de réussite');
    }

    public function testNotificationCrudListLoads(): void
    {
        $client = $this->createAdminClient();

        // A postponed notification exercises the status badge and the repop column.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $notification = new Notification($this->createType(), new \DateTimeImmutable());
        $notification->markSent();
        $notification->recordResponse(NotificationStatus::POSTPONED);
        $notification->setPostponedUntil(new \DateTimeImmutable('+15 minutes'));
        $em->persist($notification);
        $em->flush();

        $crawler = $client->request('GET', '/admin');
        // Follow the "Notifications" menu entry to the CRUD index (exercises fields + filters).
        $crawler = $client->click($crawler->selectLink('Notifications')->link());

        self::assertResponseIsSuccessful();
        // The status renders as a coloured badge ("warning" for postponed).
        $this->assertGreaterThan(0, $crawler->filter('td .badge.text-bg-warning')->count());
        self::assertSelectorTextContains('body', 'Repop prévu à');
    }

    public function testNotificationTypeCrudListLoads(): void
    {
        $client = $this->createAdminClient();

        $crawler = $client->request('GET', '/admin');
        $client->click($crawler->selectLink('Types de notif')->link());

        self::assertResponseIsSuccessful();
    }

    public function testSettingsCrudLoads(): void
    {
        $client = $this->createAdminClient();

        $crawler = $client->request('GET', '/admin');
        $client->click($crawler->selectLink('Réglages')->link());

        self::assertResponseIsSuccessful();
    }

    public function testNotificationTypeEditUsesNativeTimePickers(): void
    {
        $client = $this->createAdminClient();
        $type = $this->createType();

        $crawler = $client->request('GET', $this->crudUrl(Action::EDIT, (string) $type->getId()));

        self::assertResponseIsSuccessful();

        // The two window fields render as native <input type="time">, prefilled
        // from the "HH:MM" strings.
        $inputs = $crawler->filter('form input[type="time"]');
        $this->assertCount(2, $inputs);
        $this->assertSame('08:00', $inputs->first()->attr('value'));
        $this->assertSame('20:00', $inputs->last()->attr('value'));
    }

    public function testNotificationTypeEditRoundTripsTheTimeStrings(): void
    {
        $client = $this->createAdminClient();
        $type = $this->createType();
        $id = $type->getId();

        $crawler = $client->request('GET', $this->crudUrl(Action::EDIT, (string) $id));
        $form = $crawler->filter('form[name="NotificationType"]')->form();
        $form['NotificationType[windowStart]'] = '09:30';
        $client->submit($form);

        self::assertResponseRedirects();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $fresh = $em->getRepository(NotificationType::class)->find($id);
        $this->assertSame('09:30', $fresh->getWindowStart(), 'TimeType must convert back to the "HH:MM" string.');
        $this->assertSame('20:00', $fresh->getWindowEnd());
    }

    public function testDashboardStatsDefaultToTheCurrentWeek(): void
    {
        $client = $this->createAdminClient();
        $this->createValidatedNotification(new \DateTimeImmutable('today 10:00'));
        $this->createValidatedNotification(new \DateTimeImmutable('-60 days'));

        $crawler = $client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Semaine du');
        $this->assertSame('1', $this->deliveredCount($crawler), 'Only this week\'s notification counts by default.');
    }

    public function testDashboardGlobalPeriodCoversAllTime(): void
    {
        $client = $this->createAdminClient();
        $this->createValidatedNotification(new \DateTimeImmutable('today 10:00'));
        $this->createValidatedNotification(new \DateTimeImmutable('-60 days'));

        $crawler = $client->request('GET', '/admin?period=all');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Global (depuis le début)');
        $this->assertSame('2', $this->deliveredCount($crawler));
    }

    public function testDashboardCanBrowseAGivenMonth(): void
    {
        $client = $this->createAdminClient();
        $this->createValidatedNotification(new \DateTimeImmutable('2026-04-10 10:00'));

        $crawler = $client->request('GET', '/admin?period=month&anchor=2026-04-15');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Avril 2026');
        $this->assertSame('1', $this->deliveredCount($crawler));
    }

    public function testAdminPagesEmbedTheLiveRefreshScript(): void
    {
        $client = $this->createAdminClient();

        $crawler = $client->request('GET', '/admin');

        // Injected by DashboardController::configureAssets() on every admin page:
        // subscribes to the Mercure hub and reloads when a notification changes.
        $script = $crawler->filter('script[src="/js/admin-live-refresh.js"]');
        $this->assertCount(1, $script);
        $this->assertStringEndsWith('/.well-known/mercure', $script->attr('data-hub'));
        $this->assertSame(NotificationChangeBroadcaster::TOPIC, $script->attr('data-topic'));
    }

    public function testDashboardRedirectsAnonymousToDiscordLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        // Seamless entry point: anonymous access starts the Discord login directly.
        self::assertResponseRedirects();
        $this->assertStringContainsString('/connect/discord', $client->getResponse()->headers->get('Location') ?? '');
    }

    private function createType(): NotificationType
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        $type = (new NotificationType())
            ->setKey('admin_' . uniqid())
            ->setLabel('Type à éditer')
            ->setTitle('Titre')
            ->setMessage('Message')
        ;
        $em->persist($type);
        $em->flush();

        return $type;
    }

    private function createValidatedNotification(\DateTimeImmutable $scheduledAt): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $notification = new Notification($this->createType(), $scheduledAt);
        $notification->markSent();
        $notification->recordResponse(NotificationStatus::VALIDATED);
        $em->persist($notification);
        $em->flush();
    }

    /** Number shown on the dashboard's "Notifications délivrées" card. */
    private function deliveredCount(Crawler $crawler): string
    {
        return trim($crawler->filterXPath('//div[contains(@class, "card-body")][contains(., "Notifications délivrées")]/h2')->text());
    }

    private function crudUrl(string $action, string $entityId): string
    {
        return self::getContainer()->get(AdminUrlGenerator::class)
            ->setController(NotificationTypeCrudController::class)
            ->setAction($action)
            ->setEntityId($entityId)
            ->generateUrl()
        ;
    }
}
