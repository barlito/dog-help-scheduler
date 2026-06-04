<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Enum\NotificationStatus;
use App\Message\SendNotificationMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class NotificationResponseControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    private function createSentNotification(): Notification
    {
        $type = (new NotificationType())
            ->setKey('resp_' . uniqid())
            ->setLabel('Test')
            ->setTitle('Test')
            ->setMessage('Test message')
        ;
        $this->em->persist($type);

        $notification = new Notification($type, new \DateTimeImmutable());
        $notification->markSent();
        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    public function testValidResponseRecordsTheStatus(): void
    {
        $notification = $this->createSentNotification();
        $id = $notification->getId();

        $this->client->request('POST', \sprintf('/n/%s/%s/validated', $id, $notification->getResponseToken()));

        self::assertResponseIsSuccessful();

        $this->em->clear();
        $fresh = $this->em->getRepository(Notification::class)->find($id);
        $this->assertSame(NotificationStatus::VALIDATED, $fresh->getStatus());
        $this->assertNotNull($fresh->getRespondedAt());
    }

    public function testPostponeReschedulesUsingTheTypeDelay(): void
    {
        $notification = $this->createSentNotification();
        $id = $notification->getId();

        $this->client->request('POST', \sprintf('/n/%s/%s/postponed', $id, $notification->getResponseToken()));

        self::assertResponseIsSuccessful();

        $this->em->clear();
        $fresh = $this->em->getRepository(Notification::class)->find($id);
        $this->assertSame(NotificationStatus::POSTPONED, $fresh->getStatus());

        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');
        $this->assertCount(1, $transport->getSent());
        $this->assertInstanceOf(SendNotificationMessage::class, $transport->getSent()[0]->getMessage());
    }

    public function testInvalidTokenReturns404(): void
    {
        $notification = $this->createSentNotification();

        $this->client->request('POST', \sprintf('/n/%s/%s/validated', $notification->getId(), 'wrong-token'));

        self::assertResponseStatusCodeSame(404);
    }

    public function testFirstResponseWins(): void
    {
        $notification = $this->createSentNotification();
        $id = $notification->getId();
        $token = $notification->getResponseToken();

        $this->client->request('POST', \sprintf('/n/%s/%s/validated', $id, $token));
        $this->client->request('POST', \sprintf('/n/%s/%s/not_done', $id, $token));

        self::assertResponseIsSuccessful();

        $this->em->clear();
        $fresh = $this->em->getRepository(Notification::class)->find($id);
        $this->assertSame(NotificationStatus::VALIDATED, $fresh->getStatus(), 'The first reply must not be overwritten.');
    }
}
