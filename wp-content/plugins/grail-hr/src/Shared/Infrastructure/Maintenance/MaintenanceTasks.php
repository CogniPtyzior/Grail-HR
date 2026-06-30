<?php
/**
 * Scheduled maintenance tasks for private storage and token hygiene.
 *
 * Cron tasks are deliberately best-effort: they should never block WordPress if a file cannot be removed.
 */

declare(strict_types=1);

namespace GrailHr\Shared\Infrastructure\Maintenance;

final class MaintenanceTasks
{
    public function cleanupTemporaryFiles(): void
    {
        $this->deleteOldFiles(WP_CONTENT_DIR . '/grail-hr-private/tmp', HOUR_IN_SECONDS);
    }

    public function cleanupExports(): void
    {
        $this->deleteOldFiles(WP_CONTENT_DIR . '/grail-hr-private/exports', 2 * DAY_IN_SECONDS);
    }

    public function cleanupLogs(): void
    {
        $this->deleteOldFiles(WP_CONTENT_DIR . '/grail-hr-private/logs', 14 * DAY_IN_SECONDS, '/^grail-hr-.*\.log$/');
    }

    public function cleanupExpiredTokens(): void
    {
        global $wpdb;

        $expiredUsers = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value < %s",
            'grail_hr_token_expires_at',
            gmdate('Y-m-d H:i:s')
        ));

        foreach ((array) $expiredUsers as $userId) {
            update_user_meta((int) $userId, 'grail_hr_token_revoked_at', gmdate('Y-m-d H:i:s'));
        }
    }

    private function deleteOldFiles(string $directory, int $maxAgeSeconds, string $pattern = '/.*/'): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $now = time();
        $files = glob(rtrim($directory, '/') . '/*') ?: [];

        foreach ($files as $file) {
            if (!is_file($file) || basename($file) === 'index.php' || basename($file) === '.htaccess') {
                continue;
            }

            if (!preg_match($pattern, basename($file))) {
                continue;
            }

            if ($now - (int) filemtime($file) > $maxAgeSeconds) {
                @unlink($file);
            }
        }
    }
}
