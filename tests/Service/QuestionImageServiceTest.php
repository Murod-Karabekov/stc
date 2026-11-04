<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\QuestionImageService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class QuestionImageServiceTest extends KernelTestCase
{
    private QuestionImageService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = static::getContainer()->get(QuestionImageService::class);
    }

    public function testRejectsNonImageFiles(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmp, '<?php echo "malicious"; ?>');

        $uploadedFile = new UploadedFile($tmp, 'malicious.php', 'text/plain', null, true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Faqat PNG, JPEG va WebP formatdagi rasmlar qabul qilinadi');

        try {
            $this->service->processUpload($uploadedFile);
        } finally {
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function testRejectsSvgFiles(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmp, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $uploadedFile = new UploadedFile($tmp, 'test.svg', 'image/svg+xml', null, true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Faqat PNG, JPEG va WebP formatdagi rasmlar qabul qilinadi');

        try {
            $this->service->processUpload($uploadedFile);
        } finally {
            if (file_exists($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function testAcceptsValidPngImage(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_');
        // Valid 1x1 transparent PNG data
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        file_put_contents($tmp, $pngData);

        $uploadedFile = new UploadedFile($tmp, 'valid.png', 'image/png', null, true);

        $resultPath = $this->service->processUpload($uploadedFile);

        self::assertStringStartsWith('uploads/questions/q_', $resultPath);
        self::assertStringEndsWith('.png', $resultPath);

        // Clean up uploaded file
        $this->service->deleteImage($resultPath);
    }
}
