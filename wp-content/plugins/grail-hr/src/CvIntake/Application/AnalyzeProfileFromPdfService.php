<?php
/**
 * Orchestrates the full CV analysis process while ensuring temporary files are deleted quickly.
 */

declare(strict_types=1);

namespace GrailHr\CvIntake\Application;

use GrailHr\CvIntake\Infrastructure\CvUploadValidator;
use GrailHr\CvIntake\Infrastructure\PdfTextExtractor;
use GrailHr\CvIntake\Infrastructure\PrivateFileStorage;
use GrailHr\ProfileAnalysis\Infrastructure\OpenAiAnalysisProvider;
use GrailHr\ProfileManagement\Application\ProfileService;
use GrailHr\Shared\Infrastructure\Logging\Logger;
use GrailHr\Shared\Domain\Exception\GrailHrException;

final class AnalyzeProfileFromPdfService
{
    public function __construct(
        private readonly ProfileService $profiles,
        private readonly PrivateFileStorage $storage,
        private readonly CvUploadValidator $validator,
        private readonly PdfTextExtractor $extractor,
        private readonly OpenAiAnalysisProvider $openAi,
        private readonly Logger $logger,
        private readonly AnalysisRateLimiter $rateLimiter
    ) {
    }

    /** @param array<string, mixed> $file */
    public function createProfileFromPdf(array $file, int $userId, string $title = ''): int
    {
        $postId = $this->profiles->createShellForCv($userId, $title);

        try {
            $this->replaceAnalysis($postId, $file, $userId);
        } catch (\Throwable) {
            // The shell profile is intentionally kept so the consultant can reopen it and retry with a clearer context.
        }

        return $postId;
    }

    /** @param array<string, mixed> $file */
    public function replaceAnalysis(int $postId, array $file, int $userId): void
    {
        $tmpPath = '';
        $started = microtime(true);
        $stage = 'initializing';

        try {
            $stage = 'rate_limit';
            $this->rateLimiter->assertAllowed($userId);
            $this->rateLimiter->record($userId);
            $this->profiles->markAnalysisStatus($postId, $userId, 'pending');
            $stage = 'validate_upload';
            $this->validator->validate($file);
            $stage = 'store_upload';
            $tmpPath = $this->storage->storeUploadedPdf($file);
            $this->profiles->markAnalysisStatus($postId, $userId, 'extracting');
            $sourceMeta = [
                'source_file_hash' => hash_file('sha256', $tmpPath),
                'source_original_extension' => 'pdf',
                'source_mime_type' => sanitize_text_field((string) ($file['type'] ?? 'application/pdf')),
                'source_file_size' => (int) ($file['size'] ?? 0),
            ];
            $stage = 'extract_text';
            $text = $this->extractor->extract($tmpPath);
            $this->storage->deleteQuietly($tmpPath);
            $tmpPath = '';

            $this->profiles->markAnalysisStatus($postId, $userId, 'analyzing');
            $stage = 'openai_analysis';
            $analysis = $this->openAi->analyze($text);
            $this->profiles->markAnalysisStatus($postId, $userId, 'validating');
            $stage = 'validate_result';
            $sourceMeta['provider'] = 'openai';
            $sourceMeta['model'] = $this->openAi->modelName();
            $sourceMeta['prompt_version'] = $this->openAi->promptVersion();
            $sourceMeta['analysis_duration_ms'] = (int) ((microtime(true) - $started) * 1000);
            $stage = 'save_analysis';
            $this->profiles->saveAnalysis($postId, $analysis, $userId, 'completed', $sourceMeta);
            $this->logger->info('cv_intake', 'CV analysis completed.', [
                'profile_id' => $postId,
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ]);
        } catch (\Throwable $exception) {
            $this->storage->deleteQuietly($tmpPath);
            $this->profiles->markAnalysisError($postId, $userId);
            $this->logger->warning('cv_intake', 'CV analysis failed.', [
                'profile_id' => $postId,
                'stage' => $stage,
                'error' => $exception::class,
                'public_code' => $exception instanceof GrailHrException ? $exception->publicCode() : '',
                'status_code' => $exception instanceof GrailHrException ? $exception->statusCode() : 0,
            ]);
            throw $exception;
        }
    }
}
