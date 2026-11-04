<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\MarkdownSanitizerService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly MarkdownSanitizerService $markdownSanitizer,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown_safe', [$this, 'renderMarkdown'], ['is_safe' => ['html']]),
        ];
    }

    public function renderMarkdown(?string $content): string
    {
        return $this->markdownSanitizer->renderToHtml($content);
    }
}
