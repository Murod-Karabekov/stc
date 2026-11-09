<?php

declare(strict_types=1);

namespace App\Service;

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

    /**
     * Downloads an image from a public URL with SSRF protection and saves it.
     * Returns the relative path like processUpload().
     *
     * @throws \InvalidArgumentException on SSRF, bad MIME, size, or network errors
     */
    public function processUrl(string $url): string
    {
        $parsed = parse_url($url);
        if (!isset($parsed['scheme'], $parsed['host']) || !in_array($parsed['scheme'], ['http', 'https'], true)) {
            throw new \InvalidArgumentException("Faqat http:// va https:// havolalar qabul qilinadi.");
        }

        $this->assertPublicHost($parsed['host']);

        $body = $this->fetchUrl($url, 3);

        $tmp = tempnam(sys_get_temp_dir(), 'stc_img_');
        try {
            file_put_contents($tmp, $body);

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($tmp);
            if (!isset(self::ALLOWED_MIME_TYPES[$mime])) {
                throw new \InvalidArgumentException("Havola rasm emas yoki qo'llab-quvvatlanmaydigan format.");
            }

            $ext  = self::ALLOWED_MIME_TYPES[$mime];
            $info = @getimagesize($tmp);
            if ($info === false) {
                throw new \InvalidArgumentException("Yuklab olingan fayl haqiqiy tasvir emas.");
            }
            [$w, $h] = $info;
            if ($w > self::MAX_DIMENSION || $h > self::MAX_DIMENSION) {
                throw new \InvalidArgumentException(sprintf("Rasm o'lchami juda katta (%dx%d).", $w, $h));
            }

            $safeFilename = sprintf('q_%s_%s.%s', bin2hex(random_bytes(12)), time(), $ext);
            $target       = $this->uploadDir . '/' . $safeFilename;
            rename($tmp, $target);
            chmod($target, 0644);

            return 'uploads/questions/' . $safeFilename;
        } finally {
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private function assertPublicHost(string $host): void
    {
        $ip = gethostbyname($host);
        if ($ip === $host) {
            throw new \InvalidArgumentException("Havola manziliga ulanib bo'lmadi.");
        }
        if (false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new \InvalidArgumentException("Bu manzil taqiqlangan (ichki tarmoq).");
        }
        // Block cloud metadata endpoints explicitly
        if (in_array($ip, ['169.254.169.254', '100.100.100.200'], true)) {
            throw new \InvalidArgumentException("Bu manzil taqiqlangan.");
        }
    }

    private function fetchUrl(string $url, int $redirectsLeft): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HEADER         => true,
            CURLOPT_USERAGENT      => 'STC-Admin/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerLen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $errno     = curl_errno($ch);
        curl_close($ch);

        if ($errno !== CURLE_OK || $response === false) {
            throw new \InvalidArgumentException("Havola yuklab bo'lmadi.");
        }

        if ($httpCode >= 300 && $httpCode < 400) {
            if ($redirectsLeft === 0) {
                throw new \InvalidArgumentException("Juda ko'p yo'naltirish (redirect).");
            }
            $header = substr((string) $response, 0, $headerLen);
            if (!preg_match('/^Location:\s*(.+)$/im', $header, $m)) {
                throw new \InvalidArgumentException("Yo'naltirish manzili topilmadi.");
            }
            $next   = trim($m[1]);
            $parsed = parse_url($next);
            if (!isset($parsed['host'])) {
                throw new \InvalidArgumentException("Noto'g'ri yo'naltirish manzili.");
            }
            $this->assertPublicHost($parsed['host']);

            return $this->fetchUrl($next, $redirectsLeft - 1);
        }

        if ($httpCode !== 200) {
            throw new \InvalidArgumentException("Havola yuklanmadi (HTTP {$httpCode}).");
        }

        $body = substr((string) $response, $headerLen);
        if (strlen($body) > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException("Rasm hajmi 5MB dan oshmasligi kerak.");
        }

        return $body;
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
