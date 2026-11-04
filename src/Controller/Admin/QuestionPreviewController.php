<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Question;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class QuestionPreviewController extends AbstractController
{
    #[Route('/admin/question/{id}/preview', name: 'admin_question_preview', methods: ['GET'])]
    public function preview(Question $question): Response
    {
        return $this->render('admin/question/preview.html.twig', [
            'question' => $question,
        ]);
    }
}
