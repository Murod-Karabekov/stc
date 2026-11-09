<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

class QuestionCrudController extends AbstractCrudController
{

    public static function getEntityFqcn(): string
    {
        return Question::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Savol')
            ->setEntityLabelInPlural('Savollar banki')
            ->setDefaultSort(['questionNumber' => 'ASC'])
            ->setSearchFields(['content', 'imageAlt']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('exam', 'Imtihon'))
            ->add(EntityFilter::new('section', 'Bo\'lim'))
            ->add(ChoiceFilter::new('difficulty', 'Qiyinlik')->setChoices([
                'Oson' => Question::DIFFICULTY_EASY,
                'O\'rta' => Question::DIFFICULTY_MEDIUM,
                'Qiyin' => Question::DIFFICULTY_HARD,
            ]))
            ->add(ChoiceFilter::new('status', 'Holat')->setChoices([
                'Qoralama' => Question::STATUS_DRAFT,
                'Tayyor' => Question::STATUS_READY,
                'Arxivlangan' => Question::STATUS_ARCHIVED,
            ]))
            ->add(TextFilter::new('content', 'Savol matni'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $previewAction = Action::new('preview', 'Ko\'rish', 'fa-solid fa-eye')
            ->linkToRoute('admin_question_preview', function (Question $question): array {
                return ['id' => $question->getId()];
            });

        $newAction = Action::new('newInEditor', '+ Yangi savol', 'fa-solid fa-plus')
            ->linkToUrl('/admin/question-editor/new')
            ->createAsGlobalAction();

        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, $previewAction)
            ->add(Crud::PAGE_INDEX, $newAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $a) => $a->linkToUrl(fn(Question $q): string => '/admin/question-editor/' . $q->getId() . '/edit'));
    }

    public function new(\EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext $context): \EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore|\Symfony\Component\HttpFoundation\Response
    {
        return $this->redirect('/admin/question-editor/new');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield IntegerField::new('questionNumber', '№');
        yield AssociationField::new('section', 'Bo\'lim');
        yield AssociationField::new('exam', 'Imtihon');
        yield TextareaField::new('content', 'Savol matni (Markdown)');

        yield ImageField::new('imagePath', 'Rasm')
            ->setBasePath('/')
            ->setUploadDir('public/uploads/questions')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->setHelp('PNG, JPG, WebP. Maksimal 5MB.')
            ->setRequired(false);

        yield TextField::new('imageAlt', 'Rasm tavsifi (Alt text)')->hideOnIndex();

        yield ChoiceField::new('difficulty', 'Qiyinlik')
            ->setChoices([
                'Oson' => Question::DIFFICULTY_EASY,
                'O\'rta' => Question::DIFFICULTY_MEDIUM,
                'Qiyin' => Question::DIFFICULTY_HARD,
            ]);

        yield IntegerField::new('points', 'Ball');

        yield ChoiceField::new('status', 'Holat')
            ->setChoices([
                'Qoralama' => Question::STATUS_DRAFT,
                'Tayyor' => Question::STATUS_READY,
                'Arxivlangan' => Question::STATUS_ARCHIVED,
            ])
            ->renderAsBadges([
                Question::STATUS_DRAFT => 'warning',
                Question::STATUS_READY => 'success',
                Question::STATUS_ARCHIVED => 'secondary',
            ]);

        yield TextareaField::new('explanation', 'Izoh (tashkilotchilar uchun)')->hideOnIndex();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Question) {
            $entityInstance->setStatus(Question::STATUS_ARCHIVED);
            $entityManager->flush();
        }
    }
}
