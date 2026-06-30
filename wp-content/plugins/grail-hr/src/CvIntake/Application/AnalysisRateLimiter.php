<?php
/**
 * Per-user hourly analysis limiter. It protects OpenAI usage even if the frontend button state is bypassed.
 */

declare(strict_types=1);

namespace GrailHr\CvIntake\Application;

use GrailHr\Settings\Infrastructure\SettingsRepository;
use GrailHr\Shared\Domain\Exception\GrailHrException;

final class AnalysisRateLimiter
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function assertAllowed(int $userId): void
    {
        $key = $this->key($userId);
        $count = (int) get_transient($key);

        if ($count >= $this->settings->analysesPerHour()) {
            throw new GrailHrException(
                'La limite d’analyses par heure est atteinte. Veuillez réessayer plus tard ou contacter l’administrateur du site.',
                'grail_hr_rate_limit_exceeded',
                429
            );
        }
    }

    public function remaining(int $userId): int
    {
        $limit = $this->settings->analysesPerHour();
        $count = (int) get_transient($this->key($userId));

        return max(0, $limit - $count);
    }

    public function record(int $userId): void
    {
        $key = $this->key($userId);
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
    }

    private function key(int $userId): string
    {
        return 'grail_hr_analysis_rate_' . $userId . '_' . gmdate('YmdH');
    }
}
