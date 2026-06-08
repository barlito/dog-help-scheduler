<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\NotificationType;
use App\Service\DayPlanner;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\HttpFoundation\Response;

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

    public function configureActions(Actions $actions): Actions
    {
        // Plan this type's notifications for today on demand (recovery after a missed
        // schedule, or to test a freshly-tuned window). Idempotent for the day.
        $planNow = Action::new('planNow', 'Générer aujourd\'hui', 'fa fa-wand-magic-sparkles')
            ->linkToCrudAction('planNow')
        ;

        // The detail page is the only place showing every field (id, message, tags…).
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $planNow)
            ->add(Crud::PAGE_DETAIL, $planNow)
        ;
    }

    #[AdminRoute('/{entityId:notificationType.id}/plan-now')]
    public function planNow(NotificationType $notificationType, DayPlanner $planner, AdminUrlGenerator $adminUrlGenerator): Response
    {
        $count = $planner->planType($notificationType);

        if ($count > 0) {
            $this->addFlash('success', \sprintf('%d notification(s) générée(s) pour aujourd\'hui.', $count));
        } else {
            $this->addFlash('warning', 'Rien à générer : ce type est déjà planifié pour aujourd\'hui.');
        }

        return $this->redirect(
            $adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl(),
        );
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
        yield $this->timeField('windowStart', 'Début fenêtre');
        yield $this->timeField('windowEnd', 'Fin fenêtre');
        yield IntegerField::new('perDay', 'Nb / jour');
        yield IntegerField::new('minGapMinutes', 'Écart min (min)');
        yield IntegerField::new('postponeMinutes', 'Report (min)')
            ->setHelp('Délai de renvoi quand tu tapes "Reporter".')
        ;
        yield IntegerField::new('postponeJitterMaxMinutes', 'Aléa report (min)')
            ->setHelp('Aléa ajouté au report : tirage entre 1 et N min (0 = désactivé).')
        ;
        yield DateTimeField::new('updatedAt', 'Modifié le')->setFormat('dd/MM/yyyy HH:mm')->hideOnForm();
        yield DateTimeField::new('createdAt', 'Créé le')->setFormat('dd/MM/yyyy HH:mm')->onlyOnDetail();
    }

    /**
     * Native browser time picker over the "HH:MM" string properties: Symfony's
     * TimeType renders an <input type="time"> and converts from/to the string.
     */
    private function timeField(string $property, string $label): TextField
    {
        return TextField::new($property, $label)
            ->setFormType(TimeType::class)
            ->setFormTypeOptions([
                'widget' => 'single_text',
                'input' => 'string',
                'input_format' => 'H:i',
            ])
        ;
    }
}
