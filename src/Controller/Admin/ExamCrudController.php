<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Exam;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ExamCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Exam::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Tanlov')
            ->setEntityLabelInPlural('Saralash')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $publishAction = Action::new('publishResults', 'Natijalarni e\'lon qilish', 'fa-solid fa-bullhorn')
            ->linkToRoute('admin_exam_publish_confirm', fn (Exam $e) => ['id' => $e->getId()])
            ->displayIf(fn (Exam $e) => !$e->isPublished())
            ->setCssClass('btn btn-sm btn-warning');

        return $actions
            ->add(Crud::PAGE_INDEX, $publishAction)
            ->add(Crud::PAGE_DETAIL, $publishAction)
            ->disable(Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title', 'Nomi');
        yield TextField::new('code', 'Kodi (Slug)');
        yield IntegerField::new('durationMinutes', 'Davomiyligi (daqiqa)');
        yield BooleanField::new('isOpen', 'Qabul ochiq');
        yield TextareaField::new('description', 'Tavsif')->hideOnIndex();

        // isPublished shown read-only on index/detail; editing done through the publish action
        yield BooleanField::new('isPublished', 'Natijalar e\'lon qilingan')
            ->hideOnForm()
            ->renderAsSwitch(false);

        yield DateTimeField::new('publishedAt', 'E\'lon qilingan vaqti')
            ->hideOnForm()
            ->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Yaratilgan')->hideOnForm()->hideOnIndex();
        yield DateTimeField::new('updatedAt', 'Tahrirlangan')->hideOnForm()->hideOnIndex();
    }
}
