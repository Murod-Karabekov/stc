<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AttemptReset;
use App\Entity\Exam;
use App\Entity\ExamAttempt;
use App\Entity\Question;
use App\Entity\Section;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public function index(): Response
    {
        // Redirect to Exam CRUD by default or render custom dashboard overview
        return $this->redirect($this->adminUrlGenerator->setController(ExamCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('JDPU STC Platform')
            ->setFaviconPath('favicon.ico')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Boshqaruv', 'fa fa-home');

        yield MenuItem::section('Imtihon va Savollar');
        yield MenuItem::linkToCrud('Imtihonlar', 'fa fa-file-text', Exam::class);
        yield MenuItem::linkToCrud('Bo\'limlar', 'fa fa-folder', Section::class);
        yield MenuItem::linkToCrud('Savollar banki', 'fa fa-question-circle', Question::class);

        yield MenuItem::section('Nomzodlar va Natijalar');
        yield MenuItem::linkToCrud('Urinishlar va natijalar', 'fa fa-users', ExamAttempt::class);
        yield MenuItem::linkToCrud('Auditorlik qayta tiklashlar', 'fa fa-history', AttemptReset::class);

        yield MenuItem::section('Tizim');
        yield MenuItem::linkToUrl('Natijalar CSV eksporti', 'fa fa-download', '/admin/export/csv');
        yield MenuItem::linkToRoute('Bosh sahifa', 'fa fa-globe', 'app_home');
        yield MenuItem::linkToRoute('Chiqish', 'fa fa-sign-out', 'app_admin_logout');
    }
}
