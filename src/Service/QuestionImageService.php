<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\KernelInterface;

final class QuestionImageService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    private const MAX_DIMENSION = 4000;

    private string $uploadDir;

    public function __construct(KernelInterface $kernel)
    {
        $this->uploadDir = $kernel->getProjectDir() . '/public/uploads/questions';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Validates and saves an uploaded question image.
     * Re-encodes image or strips metadata and returns the relative path.
     *
     * @throws \InvalidArgumentException
     */
    public function processUpload(UploadedFile $file): string
    {
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('Rasm hajmi 5MB dan oshmasligi kerak.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file->getPathname());

        if (!isset(self::ALLOWED_MIME_TYPES[$realMime])) {
            throw new \InvalidArgumentException('Faqat PNG, JPEG va WebP formatdagi rasmlar qabul qilinadi. SVG yoki boshqa fayllar taqiqlangan.');
        }

        $ext = self::ALLOWED_MIME_TYPES[$realMime];

        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('Fayl haqiqiy tasvir emas yoki shikastlangan.');
        }

        [$width, $height] = $imageInfo;
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new \InvalidArgumentException(sprintf('Rasm o\'lchami juda katta (%dx%d). Ruxsat etilgan maksimal o\'lcham: %dx%d.', $width, $height, self::MAX_DIMENSION, self::MAX_DIMENSION));
        }

        // Generate server-controlled cryptographic filename
        $safeFilename = sprintf('q_%s_%s.%s', bin2hex(random_bytes(12)), time(), $ext);
        $targetPath = $this->uploadDir . '/' . $safeFilename;

        // Re-encode or safely move to strip executable blobs
        $reencoded = false;
        if (function_exists('imagecreatefromstring')) {
            $raw = file_get_contents($file->getPathname());
            if ($raw !== false) {
                $im = @imagecreatefromstring($raw);
                if ($im !== false) {
                    if ($ext === 'jpg') {
                        imagejpeg($im, $targetPath, 90);
                        $reencoded = true;
                    } elseif ($ext === 'png') {
                        imagepng($im, $targetPath, 8);
                        $reencoded = true;
                    } elseif ($ext === 'webp' && function_exists('imagewebp')) {
                        imagewebp($im, $targetPath, 90);
                        $reencoded = true;
                    }
                    imagedestroy($im);
                }
            }
        }

        if (!$reencoded) {
            $file->move($this->uploadDir, $safeFilename);
        }

        return 'uploads/questions/' . $safeFilename;
    }

    public function deleteImage(?string $relativePath): void
    {
        if (!$relativePath) {
            return;
        }

        $filename = basename($relativePath);
        $fullPath = $this->uploadDir . '/' . $filename;
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}
