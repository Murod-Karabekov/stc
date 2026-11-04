<?php

declare(strict_types=1);

namespace App\Service;

final class MarkdownSanitizerService
{
    /**
     * Renders Markdown text to secure HTML:
     * - Disables raw HTML (HTML entities escaped first).
     * - Parses headings, bold, italic, code blocks, lists, and tables.
     * - Prevents stored XSS.
     */
    public function renderToHtml(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        // 1. Strictly escape all existing HTML characters first to defeat any injection
        $text = htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Normalize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // 2. Fenced code blocks
        $text = preg_replace_callback('/```([a-zA-Z0-9_-]*)\n([\s\S]*?)\n```/', function ($matches) {
            $lang = $matches[1] ? sprintf(' class="language-%s"', $matches[1]) : '';
            return sprintf('<pre class="p-3 my-2 rounded bg-zinc-900 border border-zinc-800 text-zinc-200 overflow-x-auto font-mono text-xs"><code%s>%s</code></pre>', $lang, trim($matches[2]));
        }, $text);

        // 3. Inline code
        $text = preg_replace('/`([^`]+)`/', '<code class="px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-200 font-mono text-xs">$1</code>', $text);

        // 4. Blockquotes
        $text = preg_replace('/^&gt;\s+(.*)$/m', '<blockquote class="border-l-2 border-zinc-500 pl-3 my-2 text-zinc-400 italic text-sm">$1</blockquote>', $text);

        // 5. Headings
        $text = preg_replace('/^### (.*)$/m', '<h4 class="text-sm font-semibold text-zinc-100 mt-3 mb-1.5 font-mono">$1</h4>', $text);
        $text = preg_replace('/^## (.*)$/m', '<h3 class="text-base font-semibold text-zinc-100 mt-4 mb-2 font-mono">$1</h3>', $text);
        $text = preg_replace('/^# (.*)$/m', '<h2 class="text-lg font-bold text-zinc-100 mt-5 mb-2 font-mono">$1</h2>', $text);

        // 6. Bold and Italic
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong class="font-semibold text-zinc-100">$1</strong>', $text);
        $text = preg_replace('/\*([^*]+)\*/', '<em class="italic text-zinc-300">$1</em>', $text);

        // 7. Tables (| ... | ... |)
        $text = $this->parseMarkdownTables($text);

        // 8. Unordered Lists
        $text = preg_replace_callback('/((?:^[ \t]*[-*]\s+.*$\n?)+)/m', function ($matches) {
            $items = preg_replace('/^[ \t]*[-*]\s+(.*)$/m', '<li class="ml-4 list-disc text-zinc-300">$1</li>', trim($matches[1]));
            return sprintf('<ul class="my-2 space-y-1">%s</ul>', $items);
        }, $text);

        // 9. Ordered Lists
        $text = preg_replace_callback('/((?:^[ \t]*\d+\.\s+.*$\n?)+)/m', function ($matches) {
            $items = preg_replace('/^[ \t]*\d+\.\s+(.*)$/m', '<li class="ml-4 list-decimal text-zinc-300">$1</li>', trim($matches[1]));
            return sprintf('<ol class="my-2 space-y-1">%s</ol>', $items);
        }, $text);

        // 10. Paragraphs / Linebreaks
        $paragraphs = explode("\n\n", $text);
        $result = [];
        foreach ($paragraphs as $p) {
            $trimmed = trim($p);
            if ($trimmed === '') {
                continue;
            }
            if (
                str_starts_with($trimmed, '<pre') ||
                str_starts_with($trimmed, '<ul') ||
                str_starts_with($trimmed, '<ol') ||
                str_starts_with($trimmed, '<h') ||
                str_starts_with($trimmed, '<blockquote') ||
                str_starts_with($trimmed, '<div class="table-container')
            ) {
                $result[] = $trimmed;
            } else {
                $result[] = sprintf('<p class="my-1.5 leading-relaxed text-zinc-300">%s</p>', nl2br($trimmed));
            }
        }

        return implode("\n", $result);
    }

    private function parseMarkdownTables(string $text): string
    {
        return (string) preg_replace_callback('/((?:^\|.+$\n?){2,})/m', function ($matches) {
            $lines = array_filter(array_map('trim', explode("\n", trim($matches[1]))));
            if (count($lines) < 2) {
                return $matches[1];
            }

            $html = '<div class="table-container my-3 overflow-x-auto"><table class="min-w-full divide-y divide-zinc-800 border border-zinc-800 text-xs text-left">';

            $isHeader = true;
            foreach ($lines as $i => $line) {
                // Skip markdown delimiter line |---|---|
                if (preg_match('/^\|(?:\s*:?-+:?\s*\|)+$/', $line)) {
                    $isHeader = false;
                    continue;
                }

                $cells = array_values(array_filter(explode('|', $line), fn($c) => $c !== ''));
                if ($isHeader && $i === 0) {
                    $html .= '<thead class="bg-zinc-900/80 font-mono text-zinc-300"><tr>';
                    foreach ($cells as $cell) {
                        $html .= sprintf('<th class="px-3 py-2 border border-zinc-800 font-medium">%s</th>', trim($cell));
                    }
                    $html .= '</tr></thead><tbody class="divide-y divide-zinc-800">';
                } else {
                    $html .= '<tr class="hover:bg-zinc-900/40">';
                    foreach ($cells as $cell) {
                        $html .= sprintf('<td class="px-3 py-2 border border-zinc-800 text-zinc-300">%s</td>', trim($cell));
                    }
                    $html .= '</tr>';
                }
            }

            $html .= '</tbody></table></div>';
            return $html;
        }, $text);
    }
}
