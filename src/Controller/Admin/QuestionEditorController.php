<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Question;
use App\Entity\QuestionOption;
use App\Repository\ExamRepository;
use App\Repository\QuestionRepository;
use App\Repository\SectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/question-editor')]
final class QuestionEditorController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExamRepository $examRepo,
        private readonly SectionRepository $sectionRepo,
        private readonly QuestionRepository $questionRepo,
    ) {}

    #[Route('/new', name: 'admin_question_editor_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $exams    = $this->examRepo->findAll();
        $sections = $this->sectionRepo->findAllOrdered();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('question_editor', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Xavfsizlik xatosi.');
                return $this->redirectToRoute('admin_question_editor_new');
            }

            [$errors, $data] = $this->extractAndValidate($request);

            if (!empty($errors)) {
                return $this->renderEditor(null, $exams, $sections, $errors, $data);
            }

            $question = new Question();
            $this->hydrate($question, $data);
            $question->setQuestionNumber($this->nextNumber($data['section_id']));

            for ($i = 0; $i < 4; $i++) {
                $opt = new QuestionOption();
                $opt->setContent($data['options'][$i]);
                $opt->setIsCorrect($i === (int) $data['correct_option']);
                $opt->setPosition($i + 1);
                $question->addOption($opt);
                $this->em->persist($opt);
            }

            $this->em->persist($question);
            $this->em->flush();

            if ($request->request->has('save_next')) {
                return $this->redirectToRoute('admin_question_editor_new', [
                    'exam'    => $data['exam_id'],
                    'section' => $data['section_id'],
                ]);
            }

            return $this->redirect('/admin/question');
        }

        $preselect = [
            'exam_id'    => (int) ($request->query->get('exam', 0)),
            'section_id' => (int) ($request->query->get('section', 0)),
        ];

        return $this->renderEditor(null, $exams, $sections, [], $preselect);
    }

    #[Route('/{id}/edit', name: 'admin_question_editor_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $question = $this->questionRepo->find($id);
        if (!$question) {
            throw $this->createNotFoundException('Savol topilmadi.');
        }

        $exams    = $this->examRepo->findAll();
        $sections = $this->sectionRepo->findAllOrdered();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('question_editor', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Xavfsizlik xatosi.');
                return $this->redirectToRoute('admin_question_editor_edit', ['id' => $id]);
            }

            [$errors, $data] = $this->extractAndValidate($request);

            if (!empty($errors)) {
                return $this->renderEditor($question, $exams, $sections, $errors, $data);
            }

            $this->hydrate($question, $data);
            $this->updateOptions($question, $data);
            $this->em->flush();

            if ($request->request->has('save_next')) {
                return $this->redirectToRoute('admin_question_editor_new', [
                    'exam'    => $data['exam_id'],
                    'section' => $data['section_id'],
                ]);
            }

            return $this->redirect('/admin/question');
        }

        return $this->renderEditor($question, $exams, $sections, [], []);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function renderEditor(
        ?Question $question,
        array $exams,
        array $sections,
        array $errors,
        array $overrides,
    ): Response {
        $opts = [];
        $correctIndex = 0;
        if ($question) {
            $sorted = $question->getOptions()->toArray();
            usort($sorted, fn($a, $b) => $a->getPosition() <=> $b->getPosition());
            foreach ($sorted as $i => $opt) {
                $opts[$i] = $opt->getContent();
                if ($opt->isCorrect()) {
                    $correctIndex = $i;
                }
            }
        }

        // Merge DB values with override data from a failed POST
        $f = array_merge([
            'exam_id'       => $question?->getExam()?->getId() ?? 0,
            'section_id'    => $question?->getSection()?->getId() ?? 0,
            'content'       => $question?->getContent() ?? '',
            'options'       => $opts + ['', '', '', ''],
            'correct_option'=> $correctIndex,
            'image_path'    => $question?->getImagePath() ?? '',
            'image_alt'     => $question?->getImageAlt() ?? '',
            'difficulty'    => $question?->getDifficulty() ?? Question::DIFFICULTY_MEDIUM,
            'points'        => $question?->getPoints() ?? 1,
            'explanation'   => $question?->getExplanation() ?? '',
            'status'        => $question?->getStatus() ?? Question::STATUS_DRAFT,
        ], $overrides);

        return $this->render('admin/question/editor.html.twig', [
            'question' => $question,
            'exams'    => $exams,
            'sections' => $sections,
            'f'        => $f,
            'errors'   => $errors,
        ]);
    }

    /** @return array{array<string,string>, array<string,mixed>} */
    private function extractAndValidate(Request $request): array
    {
        $raw = $request->request->all();
        $errors = [];

        $data = [
            'exam_id'       => (int) ($raw['exam_id'] ?? 0),
            'section_id'    => (int) ($raw['section_id'] ?? 0),
            'content'       => trim((string) ($raw['content'] ?? '')),
            'options'       => array_map('trim', (array) ($raw['options'] ?? [])),
            'correct_option'=> (int) ($raw['correct_option'] ?? -1),
            'image_path'    => trim((string) ($raw['image_path'] ?? '')),
            'image_alt'     => trim((string) ($raw['image_alt'] ?? '')),
            'difficulty'    => (string) ($raw['difficulty'] ?? Question::DIFFICULTY_MEDIUM),
            'points'        => max(1, (int) ($raw['points'] ?? 1)),
            'explanation'   => trim((string) ($raw['explanation'] ?? '')),
            'status'        => (string) ($raw['status'] ?? Question::STATUS_DRAFT),
        ];

        if ($data['content'] === '') {
            $errors['content'] = 'Savol matni kiritilishi shart.';
        }
        if ($data['section_id'] === 0 || !$this->sectionRepo->find($data['section_id'])) {
            $errors['section_id'] = 'Bo\'lim tanlanishi shart.';
        }

        $opts = array_values($data['options']);
        for ($i = 0; $i < 4; $i++) {
            if (empty($opts[$i])) {
                $errors["options_{$i}"] = sprintf('%s varianti bo\'sh bo\'lmasligi kerak.', chr(65 + $i));
            }
        }
        if ($data['correct_option'] < 0 || $data['correct_option'] > 3) {
            $errors['correct_option'] = 'To\'g\'ri javob tanlanishi shart.';
        }

        $allowed = [Question::DIFFICULTY_EASY, Question::DIFFICULTY_MEDIUM, Question::DIFFICULTY_HARD];
        if (!in_array($data['difficulty'], $allowed, true)) {
            $data['difficulty'] = Question::DIFFICULTY_MEDIUM;
        }
        $allowedStatus = [Question::STATUS_DRAFT, Question::STATUS_READY, Question::STATUS_ARCHIVED];
        if (!in_array($data['status'], $allowedStatus, true)) {
            $data['status'] = Question::STATUS_DRAFT;
        }

        return [$errors, $data];
    }

    private function hydrate(Question $q, array $data): void
    {
        if ($data['exam_id']) {
            $exam = $this->em->getReference(\App\Entity\Exam::class, $data['exam_id']);
            $q->setExam($exam);
        }
        $section = $this->em->getReference(\App\Entity\Section::class, $data['section_id']);
        $q->setSection($section);
        $q->setContent($data['content']);
        $q->setImagePath($data['image_path'] ?: null);
        $q->setImageAlt($data['image_alt'] ?: null);
        $q->setDifficulty($data['difficulty']);
        $q->setPoints($data['points']);
        $q->setExplanation($data['explanation'] ?: null);
        $q->setStatus($data['status']);
    }

    private function updateOptions(Question $question, array $data): void
    {
        $sorted = $question->getOptions()->toArray();
        usort($sorted, fn($a, $b) => $a->getPosition() <=> $b->getPosition());

        $opts = array_values($data['options']);
        $correct = (int) $data['correct_option'];

        foreach ($sorted as $i => $opt) {
            $opt->setContent($opts[$i]);
            $opt->setIsCorrect($i === $correct);
        }

        // If question had fewer than 4 options (edge case), add missing ones
        for ($i = count($sorted); $i < 4; $i++) {
            $opt = new QuestionOption();
            $opt->setContent($opts[$i]);
            $opt->setIsCorrect($i === $correct);
            $opt->setPosition($i + 1);
            $question->addOption($opt);
            $this->em->persist($opt);
        }
    }

    private function nextNumber(int $sectionId): int
    {
        $result = $this->em->createQuery(
            'SELECT COUNT(q.id) FROM App\Entity\Question q WHERE q.section = :sid'
        )->setParameter('sid', $sectionId)->getSingleScalarResult();

        return (int) $result + 1;
    }
}
