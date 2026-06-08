<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Enum\NotificationStatus;
use App\Repository\NotificationRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    private const PERIODS = ['week', 'month', 'all'];

    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly RequestStack $requestStack,
        #[Autowire('%env(APP_TIMEZONE)%')]
        private readonly string $timezone,
    ) {
    }

    public function index(): Response
    {
        // The parent signature takes no argument, so pull the request from the stack.
        $request = $this->requestStack->getCurrentRequest() ?? Request::create('/admin');

        $timezone = new \DateTimeZone($this->timezone);
        $today = new \DateTimeImmutable('today', $timezone);

        $period = $request->query->getString('period', 'week');
        if (!\in_array($period, self::PERIODS, true)) {
            $period = 'week';
        }

        $anchor = \DateTimeImmutable::createFromFormat('Y-m-d', $request->query->getString('anchor'), $timezone) ?: $today;
        $range = $this->range($period, $anchor);

        $counts = $this->notifications->countByStatus($range['from'], $range['to']);

        $sent = $counts[NotificationStatus::SENT->value];
        $validated = $counts[NotificationStatus::VALIDATED->value];
        $postponed = $counts[NotificationStatus::POSTPONED->value];
        $notDone = $counts[NotificationStatus::NOT_DONE->value];

        // Everything that actually reached the phone (still awaiting OR already answered).
        $delivered = $sent + $validated + $postponed + $notDone;
        $answered = $validated + $postponed + $notDone;

        return $this->render('admin/dashboard.html.twig', [
            'counts' => $counts,
            'delivered' => $delivered,
            'answered' => $answered,
            'successRate' => $delivered > 0 ? round($validated / $delivered * 100) : 0,
            'responseRate' => $delivered > 0 ? round($answered / $delivered * 100) : 0,
            'todayPlan' => $this->notifications->findForDay($today),
            'today' => $today,
            'period' => $period,
            'rangeLabel' => $range['label'],
            'anchor' => $anchor,
            'prevAnchor' => $range['prev'],
            'nextAnchor' => $range['next'],
            // Whether the displayed range contains today (drives the "Aujourd'hui" shortcut).
            'isCurrentPeriod' => null === $range['from'] || ($today >= $range['from'] && $today < $range['to']),
        ]);
    }

    /**
     * Bounds ([from, to[), display label and prev/next anchors for the stats period
     * containing the anchor date. "all" has no bounds and no navigation.
     *
     * @return array{from: ?\DateTimeImmutable, to: ?\DateTimeImmutable, label: string, prev: ?\DateTimeImmutable, next: ?\DateTimeImmutable}
     */
    private function range(string $period, \DateTimeImmutable $anchor): array
    {
        if ('week' === $period) {
            // ISO day number rather than "monday this week", whose meaning shifts on Sundays.
            $monday = $anchor->modify(\sprintf('-%d days', (int) $anchor->format('N') - 1))->setTime(0, 0);

            return [
                'from' => $monday,
                'to' => $monday->modify('+7 days'),
                'label' => \sprintf('Semaine du %s au %s', $monday->format('d/m'), $monday->modify('+6 days')->format('d/m/Y')),
                'prev' => $monday->modify('-7 days'),
                'next' => $monday->modify('+7 days'),
            ];
        }

        if ('month' === $period) {
            $first = $anchor->modify('first day of this month')->setTime(0, 0);
            $formatter = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $first->getTimezone(), null, 'MMMM yyyy');

            return [
                'from' => $first,
                'to' => $first->modify('+1 month'),
                'label' => ucfirst((string) $formatter->format($first)),
                'prev' => $first->modify('-1 month'),
                'next' => $first->modify('+1 month'),
            ];
        }

        return ['from' => null, 'to' => null, 'label' => 'Global (depuis le début)', 'prev' => null, 'next' => null];
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('🐕 Dog Help Scheduler')
            ->setTranslationDomain('messages')
            ->renderContentMaximized()
        ;
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-chart-line');
        yield MenuItem::linkTo(NotificationCrudController::class, 'Notifications', 'fa fa-bell');
        yield MenuItem::linkTo(NotificationTypeCrudController::class, 'Types de notif', 'fa fa-sliders');
        yield MenuItem::linkTo(SettingsCrudController::class, 'Réglages', 'fa fa-gear');
    }
}
