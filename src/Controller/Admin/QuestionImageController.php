<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\QuestionImageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/question-image')]
final class QuestionImageController extends AbstractController
{
    public function __construct(private readonly QuestionImageService $imageService) {}

    #[Route('/upload', name: 'admin_question_image_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('question_image', (string) $request->headers->get('X-Csrf-Token'))) {
            return $this->json(['error' => 'Xavfsizlik xatosi.'], 403);
        }

        $file = $request->files->get('image');
        if (!$file) {
            return $this->json(['error' => 'Fayl tanlanmagan.'], 422);
        }

        try {
            return $this->json($this->imageService->processUpload($file));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    #[Route('/import-url', name: 'admin_question_image_import_url', methods: ['POST'])]
    public function importUrl(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('question_image', (string) $request->headers->get('X-Csrf-Token'))) {
            return $this->json(['error' => 'Xavfsizlik xatosi.'], 403);
        }

        $url = trim((string) $request->request->get('url', ''));
        if ($url === '') {
            return $this->json(['error' => 'Havola kiritilmagan.'], 422);
        }

        try {
            return $this->json($this->imageService->processUrl($url));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }
}
