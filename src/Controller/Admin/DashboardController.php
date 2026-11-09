<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ExamAttempt;
use App\Repository\ExamAttemptRepository;
use App\Repository\ExamRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly ExamRepository $examRepository,
        private readonly ExamAttemptRepository $attemptRepository,
    ) {
    }

    public function index(): Response
    {
        $exam = $this->examRepository->findCurrentActiveExam();

        $candidateCount = $this->attemptRepository->createEligibleQueryBuilder('a')
            ->select('COUNT(DISTINCT IDENTITY(a.candidate))')
            ->getQuery()
            ->getSingleScalarResult();

        $inProgress = (int) $this->attemptRepository->createEligibleQueryBuilder('a')
            ->select('COUNT(a)')
            ->andWhere('a.status = :s')
            ->setParameter('s', ExamAttempt::STATUS_IN_PROGRESS)
            ->getQuery()
            ->getSingleScalarResult();

        $completed = (int) $this->attemptRepository->createEligibleQueryBuilder('a')
            ->select('COUNT(a)')
            ->andWhere('a.status IN (:s)')
            ->setParameter('s', [ExamAttempt::STATUS_SUBMITTED, ExamAttempt::STATUS_EXPIRED])
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin/dashboard.html.twig', [
            'exam'           => $exam,
            'candidateCount' => (int) $candidateCount,
            'inProgress'     => $inProgress,
            'completed'      => $completed,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('JDPU STC Platform')
            ->setFaviconPath('favicon.ico')
            ->renderContentMaximized();
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addHtmlContentToHead('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W==" crossorigin="anonymous" referrerpolicy="no-referrer" />');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Boshqaruv', 'fa-solid fa-gauge-high');

        yield MenuItem::section('');
        yield MenuItem::linkTo(ExamCrudController::class, 'Saralash', 'fa-solid fa-graduation-cap')->setAction('index');
        yield MenuItem::linkTo(QuestionCrudController::class, 'Savollar', 'fa-solid fa-circle-question')->setAction('index');
        yield MenuItem::linkTo(ExamAttemptCrudController::class, 'Nomzodlar va natijalar', 'fa-solid fa-users')->setAction('index');

        yield MenuItem::section('');
        yield MenuItem::linkToRoute('Bosh sahifa', 'fa-solid fa-globe', 'app_home');
        yield MenuItem::linkToRoute('Chiqish', 'fa-solid fa-right-from-bracket', 'app_admin_logout');
    }
}
