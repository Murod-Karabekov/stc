<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ExamAttempt;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class ExamAttemptCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ExamAttempt::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Nomzod natijasi')
            ->setEntityLabelInPlural('Nomzodlar va natijalar')
            ->setDefaultSort(['totalScore' => 'DESC', 'id' => 'ASC'])
            ->setSearchFields(['candidateCode']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('exam', 'Imtihon'))
            ->add(ChoiceFilter::new('status', 'Holat')->setChoices([
                'Jarayonda' => ExamAttempt::STATUS_IN_PROGRESS,
                'Topshirildi' => ExamAttempt::STATUS_SUBMITTED,
                'Vaqt tugagan' => ExamAttempt::STATUS_EXPIRED,
            ]))
            ->add(ChoiceFilter::new('selectionDecision', 'Qaror')->setChoices([
                'Kutilmoqda' => ExamAttempt::DECISION_PENDING,
                'O\'tdi' => ExamAttempt::DECISION_ADVANCED,
                'Zaxira' => ExamAttempt::DECISION_RESERVE,
                'Rad etildi' => ExamAttempt::DECISION_REJECTED,
            ]))
            ->add(TextFilter::new('candidateCode', 'Nomzod kodi'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $decideAction = Action::new('setDecision', 'Qaror', 'fa-solid fa-gavel')
            ->linkToRoute('admin_attempt_decision', fn (ExamAttempt $a) => ['id' => $a->getId()])
            ->displayIf(fn (ExamAttempt $a) => $a->getStatus() !== ExamAttempt::STATUS_IN_PROGRESS)
            ->setCssClass('btn btn-sm btn-outline-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $decideAction)
            ->add(Crud::PAGE_DETAIL, $decideAction)
            ->disable(Action::NEW, Action::DELETE, Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('candidateCode', 'Nomzod kodi');
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

        yield IntegerField::new('totalScore', 'Ball');
        yield IntegerField::new('maxScore', 'Maksimal ball')->hideOnIndex();

        yield ChoiceField::new('selectionDecision', 'Qaror')
            ->setChoices([
                'Kutilmoqda' => ExamAttempt::DECISION_PENDING,
                'O\'tdi' => ExamAttempt::DECISION_ADVANCED,
                'Zaxira' => ExamAttempt::DECISION_RESERVE,
                'Rad etildi' => ExamAttempt::DECISION_REJECTED,
            ])
            ->renderAsBadges([
                ExamAttempt::DECISION_PENDING => 'secondary',
                ExamAttempt::DECISION_ADVANCED => 'success',
                ExamAttempt::DECISION_RESERVE => 'info',
                ExamAttempt::DECISION_REJECTED => 'danger',
            ]);

        yield TextField::new('decisionChangedBy', 'Qaror kim tomonidan')->hideOnIndex();
        yield DateTimeField::new('decisionChangedAt', 'Qaror vaqti')->hideOnIndex();
        yield DateTimeField::new('startedAt', 'Boshlangan')->hideOnIndex();
        yield DateTimeField::new('submittedAt', 'Topshirilgan')->hideOnIndex();
    }
}
