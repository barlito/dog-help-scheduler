<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Enum\NotificationStatus;
use App\Message\SendNotificationMessage;
use App\MessageHandler\SendNotificationMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SendNotificationMessageHandlerTest extends KernelTestCase
{
    private function reset(EntityManagerInterface $em): void
    {
        $em->createQuery('DELETE FROM ' . Notification::class . ' n')->execute();
        $em->createQuery('DELETE FROM ' . NotificationType::class . ' t')->execute();
    }

    private function persistNotification(EntityManagerInterface $em): Notification
    {
        $type = (new NotificationType())
            ->setKey('walk_' . uniqid())
            ->setLabel('Fausse sortie')
            ->setTitle('Fausse sortie')
            ->setMessage('Go!')
        ;
        $notification = new Notification($type, new \DateTimeImmutable());

        $em->persist($type);
        $em->persist($notification);
        $em->flush();

        return $notification;
    }

    /**
     * Swap the scoped ntfy HTTP client for a mock so nothing hits the network and we
     * can count how many publish requests the handler actually made.
     */
    private function bootWithMockHttp(): MockHttpClient
    {
        self::bootKernel();
        $mock = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['http_code' => 200]));
        self::getContainer()->set('ntfy.client', $mock);

        return $mock;
    }

    public function testCancelledNotificationIsNotPublished(): void
    {
        $http = $this->bootWithMockHttp();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->reset($em);

        $notification = $this->persistNotification($em);
        $this->assertTrue($notification->cancel());
        $em->flush();

        self::getContainer()->get(SendNotificationMessageHandler::class)(
            new SendNotificationMessage((string) $notification->getId()),
        );

        $this->assertSame(0, $http->getRequestsCount(), 'A cancelled notification must not be published.');
        $this->assertSame(NotificationStatus::CANCELLED, $notification->getStatus());
        $this->assertNull($notification->getSentAt());
    }

    public function testPlannedNotificationIsPublishedAndMarkedSent(): void
    {
        $http = $this->bootWithMockHttp();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->reset($em);

        $notification = $this->persistNotification($em);

        self::getContainer()->get(SendNotificationMessageHandler::class)(
            new SendNotificationMessage((string) $notification->getId()),
        );

        $this->assertSame(1, $http->getRequestsCount());
        $this->assertSame(NotificationStatus::SENT, $notification->getStatus());
        $this->assertNotNull($notification->getSentAt());
    }
}
