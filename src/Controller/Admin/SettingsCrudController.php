<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Settings;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class SettingsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Settings::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Réglages')
            ->setEntityLabelInPlural('Réglages')
            ->setPageTitle('index', 'Réglages')
            ->setPageTitle('edit', 'Réglages')
            ->renderContentMaximized()
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        // Single-row config: keep the default Edit action, drop the rest.
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('ntfyTopic', 'Topic ntfy (flux)')
            ->setHelp('Le nom du flux ntfy auquel tu t\'abonnes dans l\'app. Laisser vide pour utiliser la variable d\'environnement NTFY_TOPIC.')
        ;
    }
}
