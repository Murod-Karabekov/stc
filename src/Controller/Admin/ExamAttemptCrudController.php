<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ExamAttempt;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Routing\RouterInterface;

class ExamAttemptCrudController extends AbstractCrudController
{
    public function __construct(private readonly RouterInterface $router) {}

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
            ->setSearchFields(['candidateCode']); // name/phone handled in createIndexQueryBuilder
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Fetch-join candidate to avoid N+1 queries on the index list
        $qb->leftJoin('entity.candidate', 'c_ea')
           ->addSelect('c_ea');

        // Extend search to cover candidate name and phone
        $q = trim((string) $searchDto->getQuery());
        if ($q !== '') {
            $qb->orWhere('c_ea.fullName LIKE :_cq')
               ->orWhere('c_ea.phone LIKE :_cq')
               ->setParameter('_cq', '%' . $q . '%');
        }

        return $qb;
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
            ]));
    }

    public function configureActions(Actions $actions): Actions
    {
        $decideAction = Action::new('setDecision', 'Qaror', 'fa-solid fa-gavel')
            ->linkToRoute('admin_attempt_decision', fn (ExamAttempt $a) => ['id' => $a->getId()])
            ->displayIf(fn (ExamAttempt $a) => $a->getStatus() !== ExamAttempt::STATUS_IN_PROGRESS)
            ->setCssClass('btn btn-sm btn-outline-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, $decideAction)
            ->disable(Action::NEW, Action::DELETE, Action::EDIT, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        $router = $this->router;

        // Full name as a clickable link to the custom detail page (index only)
        yield TextField::new('candidate.fullName', 'To\'liq ism')
            ->formatValue(static function (mixed $value, ExamAttempt $a) use ($router): string {
                $url = htmlspecialchars($router->generate('admin_attempt_detail', ['id' => $a->getId()]));
                $name = htmlspecialchars((string) $value);
                return sprintf('<a href="%s" class="fw-semibold text-decoration-none">%s</a>', $url, $name ?: '<em class="text-muted">—</em>');
            })
            ->renderAsHtml()
            ->onlyOnIndex();

        yield TextField::new('candidate.phone', 'Telefon')
            ->onlyOnIndex();

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

        // Show score only for finalized attempts; in-progress shows "Yakunlanmagan"
        yield TextField::new('totalScore', 'Ball')
            ->formatValue(static function (mixed $value, ExamAttempt $a): string {
                if ($a->isInProgress()) {
                    return '<span class="badge text-bg-secondary">Yakunlanmagan</span>';
                }
                return htmlspecialchars((string) $value) . ' / ' . $a->getMaxScore();
            })
            ->renderAsHtml()
            ->onlyOnIndex();

        yield IntegerField::new('totalScore', 'Jami ball')->hideOnIndex();
        yield IntegerField::new('maxScore', 'Maks. ball')->hideOnIndex();

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
