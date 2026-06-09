<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DayPlanner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:plan-day',
    description: "Plans today's notifications for every enabled type (manual run / catch-up after a missed 00:05 schedule).",
)]
final class PlanDayCommand extends Command
{
    public function __construct(
        private readonly DayPlanner $planner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->planner->planEnabled();

        // Idempotent: 0 means the day was already planned for every enabled type.
        $io->success(\sprintf('%d notification(s) planifiée(s) pour aujourd\'hui.', $count));

        return Command::SUCCESS;
    }
}
