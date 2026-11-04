<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ExamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly ?ExamRepository $examRepository = null,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        $activeExam = null;
        $isSelectionOpen = false;

        if ($this->examRepository !== null) {
            $activeExam = $this->examRepository->findCurrentActiveExam();
            $isSelectionOpen = $activeExam !== null && $activeExam->isOpen();
        }

        return $this->render('home/index.html.twig', [
            'isSelectionOpen' => $isSelectionOpen,
            'activeExam' => $activeExam,
        ]);
    }
}
