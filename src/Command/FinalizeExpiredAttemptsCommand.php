<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ExamAttempt;
use App\Repository\ExamAttemptRepository;
use App\Service\ExamAttemptService;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Finalizes all in-progress attempts whose deadline has passed.
 * Run via cron: * * * * * php /app/bin/console app:finalize-expired-attempts >> /var/log/stc_cron.log 2>&1
 */
#[AsCommand(
    name: 'app:finalize-expired-attempts',
    description: 'Scores and closes any in-progress exam attempt whose server deadline has passed.'
)]
final class FinalizeExpiredAttemptsCommand extends Command
{
    public function __construct(
        private readonly ExamAttemptRepository $attemptRepository,
        private readonly ExamAttemptService $attemptService,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone(new \DateTimeZone('Asia/Tashkent'));

        /** @var ExamAttempt[] $expired */
        $expired = $this->attemptRepository->findExpiredActiveAttempts($now);

        if (empty($expired)) {
            $io->success('Muddati o\'tgan faol urinishlar topilmadi.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($expired as $attempt) {
            try {
                $this->attemptService->finalizeExpiredAttempt($attempt);
                $count++;
                $io->writeln(sprintf(
                    '  [✓] Urinish #%d yakunlandi — %s',
                    (int) $attempt->getId(),
                    (string) $attempt->getCandidateCode()
                ));
            } catch (\Throwable $e) {
                $io->error(sprintf(
                    'Urinish #%d yakunlanmadi: %s',
                    (int) $attempt->getId(),
                    $e->getMessage()
                ));
            }
        }

        $io->success(sprintf('%d ta muddati o\'tgan urinish yakunlandi.', $count));
        return Command::SUCCESS;
    }
}
