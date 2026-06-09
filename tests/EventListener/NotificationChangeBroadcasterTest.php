<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Entity\Settings;
use App\EventListener\NotificationChangeBroadcaster;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

final class NotificationChangeBroadcasterTest extends TestCase
{
    /** @var Update[] */
    private array $published = [];

    private NotificationChangeBroadcaster $broadcaster;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $hub = new MockHub(
            'http://php/.well-known/mercure',
            new StaticTokenProvider('jwt'),
            function (Update $update): string {
                $this->published[] = $update;

                return 'id';
            },
        );

        $this->broadcaster = new NotificationChangeBroadcaster($hub, new NullLogger());
        // The Doctrine event args want an EM; the listener never touches it.
        $this->em = $this->createStub(EntityManagerInterface::class);
    }

    public function testPublishesOnceAfterAFlushTouchingNotifications(): void
    {
        // A day plan inserts several notifications in a single flush.
        $this->broadcaster->postPersist(new PostPersistEventArgs($this->notification(), $this->em));
        $this->broadcaster->postUpdate(new PostUpdateEventArgs($this->notification(), $this->em));
        $this->broadcaster->postFlush();

        $this->assertCount(1, $this->published);
        $this->assertSame([NotificationChangeBroadcaster::TOPIC], $this->published[0]->getTopics());
        // SSE events with an empty data buffer are never dispatched by EventSource.
        $this->assertNotSame('', $this->published[0]->getData());
    }

    public function testIgnoresFlushesNotTouchingNotifications(): void
    {
        $this->broadcaster->postUpdate(new PostUpdateEventArgs(new Settings(), $this->em));
        $this->broadcaster->postFlush();

        $this->assertSame([], $this->published);
    }

    public function testStateResetsBetweenFlushes(): void
    {
        $this->broadcaster->postPersist(new PostPersistEventArgs($this->notification(), $this->em));
        $this->broadcaster->postFlush();
        // Next flush involves no notification: nothing new must be published.
        $this->broadcaster->postFlush();

        $this->assertCount(1, $this->published);
    }

    public function testAnUnreachableHubNeverBreaksTheWrite(): void
    {
        $hub = new MockHub(
            'http://php/.well-known/mercure',
            new StaticTokenProvider('jwt'),
            static fn (Update $update): string => throw new \RuntimeException('hub down'),
        );
        $broadcaster = new NotificationChangeBroadcaster($hub, new NullLogger());

        $broadcaster->postPersist(new PostPersistEventArgs($this->notification(), $this->em));
        $broadcaster->postFlush();

        // No exception bubbled up: the ntfy callback / worker write must succeed.
        $this->expectNotToPerformAssertions();
    }

    private function notification(): Notification
    {
        return new Notification(new NotificationType(), new \DateTimeImmutable());
    }
}
