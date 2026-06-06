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
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class NotificationResponseControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        // Postpone staggering chains after any postponed notification still waiting to
        // repop; wipe leftovers from previous tests/runs so each test starts clean.
        $this->em->createQuery(
            \sprintf('DELETE FROM %s n WHERE n.status = :status AND n.postponedUntil > :now', Notification::class),
        )
            ->setParameter('status', NotificationStatus::POSTPONED)
            ->setParameter('now', new \DateTimeImmutable())
            ->execute()
        ;
    }

    private function createSentNotification(int $jitterMaxMinutes = 0): Notification
    {
        $type = (new NotificationType())
            ->setKey('resp_' . uniqid())
            ->setLabel('Test')
            ->setTitle('Test')
            ->setMessage('Test message')
            // Jitter off by default so the timing assertions stay deterministic.
            ->setPostponeJitterMaxMinutes($jitterMaxMinutes)
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
        $before = new \DateTimeImmutable();

        $this->client->request('POST', \sprintf('/n/%s/%s/postponed', $id, $notification->getResponseToken()));

        self::assertResponseIsSuccessful();

        $this->em->clear();
        $fresh = $this->em->getRepository(Notification::class)->find($id);
        $this->assertSame(NotificationStatus::POSTPONED, $fresh->getStatus());

        // No other postpone pending: repop is simply now + the type's delay (10 min default).
        $this->assertNotNull($fresh->getPostponedUntil());
        $this->assertGreaterThanOrEqual($before->modify('+9 minutes'), $fresh->getPostponedUntil());
        $this->assertLessThanOrEqual($before->modify('+11 minutes'), $fresh->getPostponedUntil());

        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');
        $this->assertCount(1, $transport->getSent());
        $envelope = $transport->getSent()[0];
        $this->assertInstanceOf(SendNotificationMessage::class, $envelope->getMessage());

        $delay = $envelope->last(DelayStamp::class);
        $this->assertNotNull($delay);
        $this->assertEqualsWithDelta(10 * 60 * 1000, $delay->getDelay(), 60 * 1000);
    }

    public function testBurstOfPostponesIsStaggered(): void
    {
        $first = $this->createSentNotification();
        $second = $this->createSentNotification();

        // The in-memory transport is reset between requests, so capture each
        // re-dispatch delay right after its request.
        $this->client->request('POST', \sprintf('/n/%s/%s/postponed', $first->getId(), $first->getResponseToken()));
        self::assertResponseIsSuccessful();
        $firstDelay = $this->lastDispatchDelay();

        $this->client->request('POST', \sprintf('/n/%s/%s/postponed', $second->getId(), $second->getResponseToken()));
        self::assertResponseIsSuccessful();
        $secondDelay = $this->lastDispatchDelay();

        $this->em->clear();
        $repository = $this->em->getRepository(Notification::class);
        $freshFirst = $repository->find($first->getId());
        $freshSecond = $repository->find($second->getId());

        // The second repop is chained 10 min after the first one, not at the same time.
        $this->assertEquals(
            $freshFirst->getPostponedUntil()->modify('+10 minutes'),
            $freshSecond->getPostponedUntil(),
        );

        $this->assertEqualsWithDelta(10 * 60 * 1000, $secondDelay - $firstDelay, 60 * 1000);
    }

    public function testPostponeAddsARandomJitter(): void
    {
        $notification = $this->createSentNotification(jitterMaxMinutes: 5);
        $id = $notification->getId();
        $before = new \DateTimeImmutable();

        $this->client->request('POST', \sprintf('/n/%s/%s/postponed', $id, $notification->getResponseToken()));

        self::assertResponseIsSuccessful();

        $this->em->clear();
        $fresh = $this->em->getRepository(Notification::class)->find($id);

        // 10 min base + a draw between 1 and 5 min: strictly later than the bare
        // delay, never beyond base + max (margins absorb request time and rounding).
        $this->assertGreaterThan($before->modify('+10 minutes 30 seconds'), $fresh->getPostponedUntil());
        $this->assertLessThan($before->modify('+16 minutes'), $fresh->getPostponedUntil());

        $delay = $this->lastDispatchDelay();
        $this->assertGreaterThan(10 * 60 * 1000, $delay);
        $this->assertLessThanOrEqual(15 * 60 * 1000, $delay);
    }

    /** Delay (ms) of the single message dispatched by the last request. */
    private function lastDispatchDelay(): int
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');
        $sent = $transport->getSent();
        $this->assertCount(1, $sent);
        $this->assertInstanceOf(SendNotificationMessage::class, $sent[0]->getMessage());

        $delay = $sent[0]->last(DelayStamp::class);
        $this->assertNotNull($delay);

        return $delay->getDelay();
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
