<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Service\NtfyMessageFactory;
use PHPUnit\Framework\TestCase;

final class NtfyMessageFactoryTest extends TestCase
{
    public function testBuildsContentFromTypeWithGenericActions(): void
    {
        $type = (new NotificationType())
            ->setTitle('🐕 Fausse sortie')
            ->setMessage('Tu l\'as faite ?')
            ->setTags(['dog2', 'walking'])
        ;
        $notification = new Notification($type, new \DateTimeImmutable());

        $message = (new NtfyMessageFactory('https://app.test/', 'https://app.test/icon.png'))
            ->forNotification($notification)
        ;

        // Content comes from the type config.
        $this->assertSame('🐕 Fausse sortie', $message->title);
        $this->assertSame('Tu l\'as faite ?', $message->message);
        $this->assertSame(['dog2', 'walking'], $message->tags);
        $this->assertSame('https://app.test/icon.png', $message->icon);

        // The three quick-reply buttons are generic and point at the callback.
        $this->assertCount(3, $message->actions);
        $this->assertSame('http', $message->actions[0]['action']);
        $this->assertSame('POST', $message->actions[0]['method']);
        $this->assertStringStartsWith('https://app.test/n/', $message->actions[0]['url']);
        $this->assertStringContainsString('/validated', $message->actions[0]['url']);
        $this->assertStringContainsString('/postponed', $message->actions[1]['url']);
        $this->assertStringContainsString('/not_done', $message->actions[2]['url']);
        $this->assertStringContainsString($notification->getResponseToken(), $message->actions[0]['url']);
    }

    public function testIconIsNullWhenNotConfigured(): void
    {
        $type = (new NotificationType())->setTitle('T')->setMessage('M');
        $notification = new Notification($type, new \DateTimeImmutable());

        $message = (new NtfyMessageFactory('https://app.test'))->forNotification($notification);

        $this->assertNull($message->icon);
    }
}
