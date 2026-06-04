<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\NotificationType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class NotificationTypeValidationTest extends KernelTestCase
{
    private function validator(): ValidatorInterface
    {
        self::bootKernel();

        return self::getContainer()->get(ValidatorInterface::class);
    }

    private function valid(): NotificationType
    {
        return (new NotificationType())
            ->setKey('fake_walk')
            ->setLabel('Fausse sortie')
            ->setTitle('Titre')
            ->setMessage('Message')
            ->setWindowStart('08:00')
            ->setWindowEnd('20:00')
            ->setPerDay(4)
            ->setMinGapMinutes(60)
        ;
    }

    public function testValidTypeHasNoViolations(): void
    {
        $this->assertCount(0, $this->validator()->validate($this->valid()));
    }

    public function testBlankRequiredFieldsAreRejected(): void
    {
        $type = $this->valid()->setKey('')->setMessage('');

        $this->assertGreaterThan(0, $this->validator()->validate($type)->count());
    }

    public function testWindowEndBeforeStartIsRejected(): void
    {
        $type = $this->valid()->setWindowStart('20:00')->setWindowEnd('08:00');

        $this->assertGreaterThan(0, $this->validator()->validate($type)->count());
    }

    public function testInfeasiblePlanningIsRejected(): void
    {
        // 5 notifications, 60-min gap → needs 240 min, but the window is only 60 min.
        $type = $this->valid()->setWindowStart('10:00')->setWindowEnd('11:00')->setPerDay(5)->setMinGapMinutes(60);

        $this->assertGreaterThan(0, $this->validator()->validate($type)->count());
    }
}
