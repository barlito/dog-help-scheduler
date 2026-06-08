<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\NotificationTypeCrudController;
use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Enum\NotificationStatus;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
