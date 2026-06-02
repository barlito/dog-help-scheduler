<?php

declare(strict_types=1);

namespace App\Service;

use Random\Randomizer;

/**
 * Picks N random datetimes inside a daily window while guaranteeing a minimum gap
 * between them and a uniform spread.
 *
 * Method: with N points needing a minimum gap g inside a window of W minutes, the
 * mandatory spacing consumes (N-1)*g minutes, leaving S = W-(N-1)*g of "slack".
 * Drawing N uniform values in [0, S], sorting them, then adding (i-1)*g to the i-th
 * value yields strictly increasing positions that always respect the gap and stay
 * within the window — with a uniform distribution of the slack.
 */
final class RandomScheduleGenerator
{
    public function __construct(private readonly Randomizer $randomizer = new Randomizer())
    {
    }

    /**
     * @param \DateTimeImmutable $day           any time on the target day (only the date + timezone are used)
     * @param string             $windowStart   "HH:MM"
     * @param string             $windowEnd     "HH:MM"
     * @param int                $count         number of notifications to plan
     * @param int                $minGapMinutes minimum spacing between two notifications
     *
     * @return \DateTimeImmutable[] sorted ascending
     */
    public function generate(
        \DateTimeImmutable $day,
        string $windowStart,
        string $windowEnd,
        int $count,
        int $minGapMinutes,
    ): array {
        if ($count < 1) {
            return [];
        }
        if ($minGapMinutes < 0) {
            throw new \InvalidArgumentException('The minimum gap cannot be negative.');
        }

        $start = $this->atTime($day, $windowStart);
        $end = $this->atTime($day, $windowEnd);

        $windowMinutes = (int) (($end->getTimestamp() - $start->getTimestamp()) / 60);
        if ($windowMinutes <= 0) {
            throw new \InvalidArgumentException(\sprintf('Window end "%s" must be after start "%s".', $windowEnd, $windowStart));
        }

        $slack = $windowMinutes - ($count - 1) * $minGapMinutes;
        if ($slack < 0) {
            throw new \InvalidArgumentException(\sprintf(
                'Cannot fit %d notifications with a %d-minute gap inside a %d-minute window.',
                $count,
                $minGapMinutes,
                $windowMinutes,
            ));
        }

        $offsets = [];
        for ($i = 0; $i < $count; ++$i) {
            $offsets[] = $this->randomizer->getInt(0, $slack);
        }
        sort($offsets);

        $times = [];
        foreach ($offsets as $i => $offset) {
            $minutes = $offset + $i * $minGapMinutes;
            $times[] = $start->modify(\sprintf('+%d minutes', $minutes));
        }

        return $times;
    }

    private function atTime(\DateTimeImmutable $day, string $hhmm): \DateTimeImmutable
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $matches)) {
            throw new \InvalidArgumentException(\sprintf('Invalid time "%s", expected "HH:MM".', $hhmm));
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        if ($hours > 23 || $minutes > 59) {
            throw new \InvalidArgumentException(\sprintf('Invalid time "%s".', $hhmm));
        }

        return $day->setTime($hours, $minutes, 0);
    }
}
