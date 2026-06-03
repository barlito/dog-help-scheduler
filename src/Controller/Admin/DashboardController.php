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
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        #[Autowire('%env(APP_TIMEZONE)%')]
        private readonly string $timezone,
    ) {
    }

    public function index(): Response
    {
        $counts = $this->notifications->countByStatus();

        $sent = $counts[NotificationStatus::SENT->value];
        $validated = $counts[NotificationStatus::VALIDATED->value];
        $postponed = $counts[NotificationStatus::POSTPONED->value];
        $notDone = $counts[NotificationStatus::NOT_DONE->value];

        // Everything that actually reached the phone (still awaiting OR already answered).
        $delivered = $sent + $validated + $postponed + $notDone;
        $answered = $validated + $postponed + $notDone;

        $today = new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));

        return $this->render('admin/dashboard.html.twig', [
            'counts' => $counts,
            'delivered' => $delivered,
            'answered' => $answered,
            'successRate' => $delivered > 0 ? round($validated / $delivered * 100) : 0,
            'responseRate' => $delivered > 0 ? round($answered / $delivered * 100) : 0,
            'todayPlan' => $this->notifications->findForDay($today),
            'today' => $today,
        ]);
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
    }
}
