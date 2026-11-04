<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Exam;
use App\Entity\Question;
use App\Entity\QuestionOption;
use App\Entity\Section;
use App\Repository\ExamRepository;
use App\Repository\QuestionRepository;
use App\Repository\SectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:import-questions',
    description: 'Idempotently imports questions from a structured JSON seed file with strict validation and dry-run.'
)]
final class ImportQuestionsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExamRepository $examRepository,
        private readonly SectionRepository $sectionRepository,
        private readonly QuestionRepository $questionRepository,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'Path to the questions seed JSON file', 'config/seeds/questions_seed.json')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate input and simulate import without making database changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = (string) $input->getArgument('file');
        $isDryRun = (bool) $input->getOption('dry-run');

        if (!str_starts_with($filePath, '/')) {
            $filePath = $this->kernel->getProjectDir() . '/' . $filePath;
        }

        if (!file_exists($filePath)) {
            $io->error(sprintf('Fayl topilmadi: "%s"', $filePath));
            return Command::FAILURE;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            $io->error(sprintf('Faylni o\'qib bo\'lmadi: "%s"', $filePath));
            return Command::FAILURE;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $io->error(sprintf('JSON formati yaroqsiz: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        // Validation
        if (!isset($data['exam']) || !is_array($data['exam'])) {
            $io->error('"exam" obyekti kiritilmagan.');
            return Command::FAILURE;
        }

        if (!isset($data['sections']) || !is_array($data['sections']) || count($data['sections']) < 5) {
            $io->error('Kamida 5 ta bo\'lim ("sections") kiritilishi shart.');
            return Command::FAILURE;
        }

        if (!isset($data['questions']) || !is_array($data['questions']) || count($data['questions']) !== 25) {
            $io->error(sprintf('Savollar soni aynan 25 ta bo\'lishi shart. Hozir: %d ta.', count($data['questions'] ?? [])));
            return Command::FAILURE;
        }

        $fixtureCount = 0;
        $readyCount = 0;

        foreach ($data['questions'] as $idx => $q) {
            $qNum = $q['number'] ?? ($idx + 1);
            if (empty($q['content'])) {
                $io->error(sprintf('№%d savol matni bo\'sh.', $qNum));
                return Command::FAILURE;
            }

            if (!isset($q['options']) || !is_array($q['options']) || count($q['options']) !== 4) {
                $io->error(sprintf('№%d savolda aynan 4 ta javob varianti bo\'lishi shart.', $qNum));
                return Command::FAILURE;
            }

            $correctCount = 0;
            foreach ($q['options'] as $opt) {
                if (!empty($opt['isCorrect'])) {
                    $correctCount++;
                }
            }

            if ($correctCount !== 1) {
                $io->error(sprintf('№%d savolda aynan 1 ta to\'g\'ri variant bo\'lishi shart. Hozir: %d ta.', $qNum, $correctCount));
                return Command::FAILURE;
            }

            if (!empty($q['isFixture'])) {
                $fixtureCount++;
            } else {
                $readyCount++;
            }
        }

        $io->note(sprintf(
            'Fayl tekshiruvdan o\'tdi. Jami savollar: 25 ta (Tasdiqlangan: %d ta, Sinov/Fixture: %d ta).',
            $readyCount,
            $fixtureCount
        ));

        if ($fixtureCount > 0) {
            $io->warning(sprintf(
                'Savollar to\'plamida %d ta sinov namunalari ([DEV FIXTURE]) mavjud. Imtihon qoralamada qoldiriladi va natijalar e\'lon qilinmaydi.',
                $fixtureCount
            ));
        }

        if ($isDryRun) {
            $io->success('DRY-RUN muvaffaqiyatli yakunlandi. Hech qanday ma\'lumot bazaga yozilmadi.');
            return Command::SUCCESS;
        }

        // Idempotent Persistence
        $examData = $data['exam'];
        $exam = $this->examRepository->findByCode($examData['code']);
        if (!$exam) {
            $exam = new Exam();
            $exam->setCode($examData['code']);
        }

        $exam->setTitle($examData['title'] ?? 'JDPU STC Saralash Imtihoni');
        $exam->setDescription($examData['description'] ?? null);
        $exam->setDurationMinutes((int) ($examData['durationMinutes'] ?? 60));
        $exam->setIsOpen((bool) ($examData['isOpen'] ?? true));
        // If there are fixtures, force isPublished to false
        $exam->setIsPublished($fixtureCount === 0 && !empty($examData['isPublished']));

        $this->entityManager->persist($exam);

        // Sections
        $sectionsMap = [];
        foreach ($data['sections'] as $sData) {
            $pos = (int) $sData['position'];
            $section = $this->sectionRepository->findByPosition($pos);
            if (!$section) {
                $section = new Section();
                $section->setPosition($pos);
            }
            $section->setTitle($sData['title']);
            $section->setDescription($sData['description'] ?? null);
            $this->entityManager->persist($section);
            $sectionsMap[$pos] = $section;
        }

        $this->entityManager->flush();

        // Questions
        foreach ($data['questions'] as $qData) {
            $qNum = (int) $qData['number'];
            $section = $sectionsMap[(int) $qData['sectionPosition']] ?? null;

            $question = $this->entityManager->getRepository(Question::class)->findOneBy([
                'exam' => $exam,
                'questionNumber' => $qNum,
            ]);

            if (!$question) {
                $question = new Question();
                $question->setExam($exam);
                $question->setQuestionNumber($qNum);
            }

            $question->setSection($section);
            $question->setContent($qData['content']);
            $question->setExplanation($qData['explanation'] ?? null);
            $question->setDifficulty($qData['difficulty'] ?? Question::DIFFICULTY_MEDIUM);
            $question->setPoints((int) ($qData['points'] ?? 1));
            $question->setStatus($qData['status'] ?? (empty($qData['isFixture']) ? Question::STATUS_READY : Question::STATUS_DRAFT));

            // Options: remove existing and re-create to maintain order idempotently
            foreach ($question->getOptions() as $existingOpt) {
                $this->entityManager->remove($existingOpt);
            }
            $question->getOptions()->clear();

            foreach ($qData['options'] as $pos => $optData) {
                $option = new QuestionOption();
                $option->setContent($optData['content']);
                $option->setIsCorrect((bool) $optData['isCorrect']);
                $option->setPosition($pos + 1);
                $question->addOption($option);
                $this->entityManager->persist($option);
            }

            $this->entityManager->persist($question);
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Imtihon ("%s") va 25 ta savol bazaga muvaffaqiyatli import qilindi (Idempotent).',
            $exam->getTitle()
        ));

        return Command::SUCCESS;
    }
}
