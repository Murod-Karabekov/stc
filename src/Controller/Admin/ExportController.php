<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ExamAttemptRepository;
use App\Repository\SectionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/export')]
final class ExportController extends AbstractController
{
    public function __construct(
        private readonly ExamAttemptRepository $attemptRepository,
        private readonly SectionRepository $sectionRepository,
    ) {
    }

    #[Route('/csv', name: 'admin_export_csv', methods: ['GET'])]
    public function csv(): StreamedResponse
    {
        $attempts = $this->attemptRepository->findAll();

        // Build ordered section list for consistent column headers
        $sections = $this->sectionRepository->findBy([], ['position' => 'ASC']);

        $response = new StreamedResponse(function () use ($attempts, $sections): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // BOM for Excel UTF-8 compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            $headers = [
                'Nomzod kodi',
                'To\'liq ism',
                'Telefon',
                'Imtihon',
                'Boshlangan',
                'Topshirilgan',
                'Holat',
                'Jami ball',
                'Maksimal ball',
                'Qaror',
            ];
            foreach ($sections as $section) {
                $headers[] = 'Bo\'lim ' . $section->getPosition() . ': ' . $this->sanitizeCsvCell($section->getTitle());
            }
            fputcsv($handle, $headers);

            foreach ($attempts as $attempt) {
                $candidate = $attempt->getCandidate();
                $row = [
                    $this->sanitizeCsvCell((string) $attempt->getCandidateCode()),
                    $this->sanitizeCsvCell((string) ($candidate?->getFullName() ?? '')),
                    $this->sanitizeCsvCell((string) ($candidate?->getPhone() ?? '')),
                    $this->sanitizeCsvCell((string) ($attempt->getExam()?->getTitle() ?? '')),
                    $attempt->getStartedAt()->setTimezone(new \DateTimeZone('Asia/Tashkent'))->format('Y-m-d H:i:s'),
                    $attempt->getSubmittedAt()?->setTimezone(new \DateTimeZone('Asia/Tashkent'))->format('Y-m-d H:i:s') ?? '',
                    $attempt->getStatus(),
                    $attempt->getTotalScore(),
                    $attempt->getMaxScore(),
                    $attempt->getSelectionDecision() ?? '',
                ];

                $sectionScores = $attempt->getSectionScores();
                foreach ($sections as $section) {
                    $row[] = (int) ($sectionScores[(string) $section->getPosition()] ?? 0);
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        });

        $filename = 'stc_results_' . date('Ymd_His') . '.csv';
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /**
     * Prevents spreadsheet formula injection.
     * ltrim strips leading whitespace/control chars so "\n=FORMULA" is caught;
     * space prefix is universally safe in Excel and LibreOffice (tab is not).
     */
    private function sanitizeCsvCell(string $value): string
    {
        if ($value === '') {
            return $value;
        }
        $check = ltrim($value, " \t\r\n");
        if ($check !== '' && in_array($check[0], ['=', '+', '-', '@'], true)) {
            return ' ' . $value;
        }
        return $value;
    }
}
