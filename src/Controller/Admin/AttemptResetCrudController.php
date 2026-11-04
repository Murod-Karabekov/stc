<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AttemptReset;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class AttemptResetCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AttemptReset::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Qayta tiklash auditi')
            ->setEntityLabelInPlural('Auditorlik qayta tiklashlar')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield AssociationField::new('attempt', 'Urinish');
        yield AssociationField::new('adminUser', 'Administrator');
        yield TextareaField::new('reason', 'Tiklash sababi');
        yield DateTimeField::new('resetAt', 'Vaqti');
    }
}
