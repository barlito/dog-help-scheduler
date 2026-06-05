<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Enum\NotificationStatus;
use PHPUnit\Framework\TestCase;

final class NotificationTest extends TestCase
{
    private function makeNotification(): Notification
    {
        $type = (new NotificationType())
            ->setKey('walk')
            ->setLabel('Fausse sortie')
            ->setTitle('Fausse sortie')
            ->setMessage('Go!')
        ;

        return new Notification($type, new \DateTimeImmutable());
    }

    public function testPlannedNotificationCanBeCancelled(): void
    {
        $notification = $this->makeNotification();

        $this->assertTrue($notification->isCancellable());
        $this->assertTrue($notification->cancel());
        $this->assertSame(NotificationStatus::CANCELLED, $notification->getStatus());
        $this->assertFalse($notification->isCancellable());
    }

    public function testSentNotificationCannotBeCancelled(): void
    {
        $notification = $this->makeNotification();
        $notification->markSent();

        $this->assertFalse($notification->isCancellable());
        $this->assertFalse($notification->cancel());
        $this->assertSame(NotificationStatus::SENT, $notification->getStatus());
    }

    public function testAnsweredNotificationCannotBeCancelled(): void
    {
        $notification = $this->makeNotification();
        $notification->recordResponse(NotificationStatus::VALIDATED);

        $this->assertFalse($notification->cancel());
        $this->assertSame(NotificationStatus::VALIDATED, $notification->getStatus());
    }
}
