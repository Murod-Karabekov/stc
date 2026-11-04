<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ExamAttempt;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ExamAttemptCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ExamAttempt::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Urinish')
            ->setEntityLabelInPlural('Nomzodlar urinishlari')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('candidateCode', 'Nomzod kodi');
        yield AssociationField::new('candidate', 'Nomzod (F.I.SH / Tel)');
        yield AssociationField::new('exam', 'Imtihon');
        
        yield ChoiceField::new('status', 'Holat')
            ->setChoices([
                'Jarayonda' => ExamAttempt::STATUS_IN_PROGRESS,
                'Topshirildi' => ExamAttempt::STATUS_SUBMITTED,
                'Vaqt tugagan' => ExamAttempt::STATUS_EXPIRED,
            ])
            ->renderAsBadges([
                ExamAttempt::STATUS_IN_PROGRESS => 'warning',
                ExamAttempt::STATUS_SUBMITTED => 'success',
                ExamAttempt::STATUS_EXPIRED => 'danger',
            ]);

        yield IntegerField::new('totalScore', 'Jami ball');
        yield IntegerField::new('maxScore', 'Maksimal ball');
        yield ArrayField::new('sectionScores', 'Bo\'limlar bo\'yicha ballar')->hideOnIndex();
        yield BooleanField::new('isSyncedToSheets', 'Google Sheets sinxronlash');
        yield DateTimeField::new('startedAt', 'Boshlangan');
        yield DateTimeField::new('deadlineAt', 'Muddati');
        yield DateTimeField::new('submittedAt', 'Topshirilgan');
    }
}
