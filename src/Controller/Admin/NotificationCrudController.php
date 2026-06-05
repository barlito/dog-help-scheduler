<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Notification;
use App\Enum\NotificationStatus;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

final class NotificationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Notification::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Notification')
            ->setEntityLabelInPlural('Notifications')
            ->setDefaultSort(['scheduledAt' => 'DESC'])
            ->setPageTitle('index', 'Notifications')
            ->renderContentMaximized()
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // "Cancel" a still-planned notification so the worker skips it — handy for days
        // spent with the dog away from home, where no fake departure can be staged.
        $cancel = Action::new('cancel', 'Annuler', 'fa fa-ban')
            ->linkToCrudAction('cancelNotification')
            ->displayIf(static fn (Notification $notification): bool => $notification->isCancellable())
        ;

        $cancelBatch = Action::new('cancelBatch', 'Annuler')
            ->linkToCrudAction('cancelBatch')
        ;

        // Notifications are created by the scheduler, not by hand: no create/edit/delete,
        // only read + cancel (single row action and batch action).
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $cancel)
            ->add(Crud::PAGE_DETAIL, $cancel)
            ->addBatchAction($cancelBatch)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
        ;
    }

    #[AdminRoute('/{entityId:notification.id}/cancel')]
    public function cancelNotification(Notification $notification, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator): Response
    {
        if ($notification->cancel()) {
            $em->flush();
            $this->addFlash('success', 'Notification annulée.');
        } else {
            $this->addFlash('warning', 'Cette notification ne peut plus être annulée (déjà envoyée ou répondue).');
        }

        return $this->redirect(
            $adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }

    #[AdminRoute('/cancel-batch')]
    public function cancelBatch(BatchActionDto $batchActionDto, EntityManagerInterface $em, AdminUrlGenerator $adminUrlGenerator): Response
    {
        // Fetch the whole selection in a single query (one IN (...)) rather than one
        // SELECT per id.
        $notifications = $em->getRepository(Notification::class)
            ->findBy(['id' => $batchActionDto->getEntityIds()])
        ;

        $cancelled = 0;
        foreach ($notifications as $notification) {
            if ($notification->cancel()) {
                ++$cancelled;
            }
        }
        $em->flush();

        $this->addFlash('success', \sprintf('%d notification(s) annulée(s).', $cancelled));

        return $this->redirect(
            $adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
    }

    public function configureFilters(Filters $filters): Filters
    {
        $choices = [];
        foreach (NotificationStatus::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices($choices))
            ->add(DateTimeFilter::new('scheduledAt', 'Prévue à'))
        ;
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield TextField::new('typeLabel', 'Type');
        yield DateTimeField::new('scheduledAt', 'Prévue à');
        yield DateTimeField::new('sentAt', 'Envoyée à');
        yield TextField::new('statusLabel', 'Statut');
        yield DateTimeField::new('respondedAt', 'Répondue à');
        yield DateTimeField::new('createdAt', 'Créée à')->onlyOnDetail();
    }
}
