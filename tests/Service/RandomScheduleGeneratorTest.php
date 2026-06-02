<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\RandomScheduleGenerator;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class RandomScheduleGeneratorTest extends TestCase
{
    private function day(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-06-02 00:00:00', new \DateTimeZone('Europe/Paris'));
    }

    public function testGeneratesRequestedCountWithinWindowRespectingGap(): void
    {
        $generator = new RandomScheduleGenerator(new Randomizer(new Mt19937(42)));

        $times = $generator->generate($this->day(), '08:00', '20:00', 4, 60);

        $this->assertCount(4, $times);

        $start = $this->day()->setTime(8, 0);
        $end = $this->day()->setTime(20, 0);

        $previous = null;
        foreach ($times as $time) {
            $this->assertGreaterThanOrEqual($start->getTimestamp(), $time->getTimestamp());
            $this->assertLessThanOrEqual($end->getTimestamp(), $time->getTimestamp());

            if (null !== $previous) {
                $gapMinutes = ($time->getTimestamp() - $previous->getTimestamp()) / 60;
                $this->assertGreaterThanOrEqual(60, $gapMinutes, 'Minimum gap must be respected.');
            }
            $previous = $time;
        }
    }

    public function testTimesAreSortedAscending(): void
    {
        $generator = new RandomScheduleGenerator(new Randomizer(new Mt19937(7)));

        $times = $generator->generate($this->day(), '09:00', '18:00', 5, 30);

        $timestamps = array_map(static fn (\DateTimeImmutable $t) => $t->getTimestamp(), $times);
        $sorted = $timestamps;
        sort($sorted);

        $this->assertSame($sorted, $timestamps);
    }

    public function testDeterministicForAGivenSeed(): void
    {
        $a = (new RandomScheduleGenerator(new Randomizer(new Mt19937(123))))->generate($this->day(), '08:00', '20:00', 4, 60);
        $b = (new RandomScheduleGenerator(new Randomizer(new Mt19937(123))))->generate($this->day(), '08:00', '20:00', 4, 60);

        $this->assertEquals($a, $b);
    }

    public function testReturnsEmptyArrayWhenCountIsZero(): void
    {
        $generator = new RandomScheduleGenerator();

        $this->assertSame([], $generator->generate($this->day(), '08:00', '20:00', 0, 60));
    }

    public function testThrowsWhenNotificationsCannotFit(): void
    {
        $generator = new RandomScheduleGenerator();

        $this->expectException(\InvalidArgumentException::class);
        // 5 notifications with a 120-min gap need 480 min, but the window is only 120 min.
        $generator->generate($this->day(), '10:00', '12:00', 5, 120);
    }

    public function testThrowsWhenWindowEndBeforeStart(): void
    {
        $generator = new RandomScheduleGenerator();

        $this->expectException(\InvalidArgumentException::class);
        $generator->generate($this->day(), '20:00', '08:00', 3, 30);
    }
}
