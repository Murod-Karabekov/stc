<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Exam;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
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
            ->setEntityLabelInSingular('Imtihon')
            ->setEntityLabelInPlural('Imtihonlar')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('title', 'Nomi');
        yield TextField::new('code', 'Kodi (Slug)');
        yield IntegerField::new('durationMinutes', 'Davomiyligi (daqiqa)');
        yield BooleanField::new('isOpen', 'Qabul ochiq (Is Open)');
        yield BooleanField::new('isPublished', 'Natijalar e\'lon qilingan');
        yield TextareaField::new('description', 'Tavsif')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Yaratilgan')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Tahrirlangan')->hideOnForm();
    }
}
