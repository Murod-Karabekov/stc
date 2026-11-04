<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CandidateController extends AbstractController
{
    #[Route('/saralash', name: 'app_candidate_register', methods: ['GET', 'POST'])]
    public function register(): Response
    {
        return $this->render('candidate/register.html.twig');
    }

    #[Route('/saralash/yoriqnoma', name: 'app_candidate_instructions', methods: ['GET'])]
    public function instructions(): Response
    {
        return $this->render('candidate/instructions.html.twig');
    }

    #[Route('/saralash/test', name: 'app_candidate_exam', methods: ['GET'])]
    public function exam(): Response
    {
        return $this->render('candidate/exam.html.twig');
    }

    #[Route('/saralash/yakun', name: 'app_candidate_completed', methods: ['GET'])]
    public function completed(): Response
    {
        return $this->render('candidate/completed.html.twig');
    }
}
