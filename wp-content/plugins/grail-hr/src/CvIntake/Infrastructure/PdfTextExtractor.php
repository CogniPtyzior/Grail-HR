<?php
/**
 * Extracts text from PDF files using pdftotext when available and a conservative fallback otherwise.
 *
 * The fallback is deliberately limited. If no meaningful text is extracted, the UI explains that OCR is not available in V1.
 */

declare(strict_types=1);

namespace GrailHr\CvIntake\Infrastructure;

use GrailHr\Shared\Domain\Exception\GrailHrException;

final class PdfTextExtractor
{
    public function extract(string $path): string
    {
        $text = $this->extractWithPdftotext($path) ?: $this->extractFallback($path);
        $text = trim(preg_replace('/\s+/', ' ', $text) ?: '');

        if (mb_strlen($text) < 120) {
            throw new GrailHrException(
                'Le CV ne contient pas de texte exploitable. L’OCR n’est pas disponible dans cette version.',
                'grail_hr_pdf_not_extractable',
                422
            );
        }

        return $text;
    }

    private function extractWithPdftotext(string $path): string
    {
        $binary = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));

        if ($binary === '') {
            return '';
        }

        $command = escapeshellcmd($binary) . ' -layout ' . escapeshellarg($path) . ' - 2>/dev/null';

        return (string) shell_exec($command);
    }

    private function extractFallback(string $path): string
    {
        $contents = (string) file_get_contents($path);
        preg_match_all('/\(([^\)]{3,})\)/', $contents, $matches);

        return implode(' ', $matches[1] ?? []);
    }
}
