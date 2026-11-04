<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Question;
use App\Entity\QuestionOption;
use App\Service\QuestionImageService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class QuestionCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly QuestionImageService $imageService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Question::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Savol')
            ->setEntityLabelInPlural('Savollar banki')
            ->setDefaultSort(['questionNumber' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $previewAction = Action::new('preview', 'Ko\'rish (Preview)', 'fa fa-eye')
            ->linkToRoute('admin_question_preview', function (Question $question): array {
                return ['id' => $question->getId()];
            });

        return $actions
            ->add(Crud::PAGE_INDEX, $previewAction)
            ->add(Crud::PAGE_DETAIL, $previewAction);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield IntegerField::new('questionNumber', '№');
        yield AssociationField::new('section', 'Bo\'lim');
        yield AssociationField::new('exam', 'Imtihon');
        yield TextareaField::new('content', 'Savol matni (Markdown)');
        
        yield ImageField::new('imagePath', 'Rasm biriktirish')
            ->setBasePath('/')
            ->setUploadDir('public/uploads/questions')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->setHelp('Faqat PNG, JPG, WebP formatlar qabul qilinadi. Maksimal hajm: 5MB.')
            ->setRequired(false);

        yield TextField::new('imageAlt', 'Rasm tavsifi (Alt text)')->hideOnIndex();
        
        yield ChoiceField::new('difficulty', 'Qiyinlik darajasi')
            ->setChoices([
                'Oson (Easy)' => Question::DIFFICULTY_EASY,
                'O\'rta (Medium)' => Question::DIFFICULTY_MEDIUM,
                'Qiyin (Hard)' => Question::DIFFICULTY_HARD,
            ]);

        yield IntegerField::new('points', 'Ball');

        yield ChoiceField::new('status', 'Holati')
            ->setChoices([
                'Qoralama (Draft)' => Question::STATUS_DRAFT,
                'Tayyor (Ready)' => Question::STATUS_READY,
                'Arxivlangan (Archived)' => Question::STATUS_ARCHIVED,
            ]);

        yield TextareaField::new('explanation', 'Izoh (Tashkilotchilar uchun)')->hideOnIndex();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        // Safe archival instead of destructive deletion
        if ($entityInstance instanceof Question) {
            $entityInstance->setStatus(Question::STATUS_ARCHIVED);
            $entityManager->flush();
        }
    }
}
