<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PlanDayMessage;
use App\Service\DayPlanner;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PlanDayMessageHandler
{
    public function __construct(
        private readonly DayPlanner $planner,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(APP_TIMEZONE)%')]
        private readonly string $timezone,
    ) {
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    public function __invoke(PlanDayMessage $message): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));
        $count = $this->planner->planEnabled($now);

        $this->logger->info('PlanDay scheduled {count} notifications for {day}.', [
            'count' => $count,
            'day' => $now->format('Y-m-d'),
        ]);
    }
}
