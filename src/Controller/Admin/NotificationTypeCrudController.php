<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\NotificationType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class NotificationTypeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return NotificationType::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Type de notification')
            ->setEntityLabelInPlural('Types de notification')
            ->setDefaultSort(['label' => 'ASC'])
            ->setPageTitle('index', 'Types de notification')
            ->renderContentMaximized()
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield TextField::new('key', 'Clé')
            ->setHelp('Identifiant machine stable, ex. "fake_walk".')
        ;
        yield TextField::new('label', 'Nom');
        yield BooleanField::new('enabled', 'Activé')
            ->setHelp('Décoche pour que le planificateur ignore ce type.')
        ;
        yield TextField::new('title', 'Titre notif')->hideOnIndex();
        yield TextareaField::new('message', 'Message')->hideOnIndex();
        yield ArrayField::new('tags', 'Tags ntfy')->hideOnIndex()
            ->setHelp('Emojis/mots-clés ntfy, ex. "dog2", "walking".')
        ;
        yield TextField::new('windowStart', 'Début fenêtre')
            ->setFormTypeOption('attr', ['type' => 'time'])
        ;
        yield TextField::new('windowEnd', 'Fin fenêtre')
            ->setFormTypeOption('attr', ['type' => 'time'])
        ;
        yield IntegerField::new('perDay', 'Nb / jour');
        yield IntegerField::new('minGapMinutes', 'Écart min (min)')->hideOnIndex();
    }
}
