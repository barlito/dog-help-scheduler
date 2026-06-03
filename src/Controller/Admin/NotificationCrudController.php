<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Notification;
use App\Enum\NotificationStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;

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
            ->setPageTitle('index', 'Notifications');
    }

    public function configureActions(Actions $actions): Actions
    {
        // Read-only backoffice: notifications are created by the scheduler, not by hand.
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $choices = [];
        foreach (NotificationStatus::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices($choices))
            ->add(DateTimeFilter::new('scheduledAt', 'Prévue à'));
    }

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
