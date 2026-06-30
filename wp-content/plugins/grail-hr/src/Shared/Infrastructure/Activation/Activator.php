<?php
/**
 * Activation and deactivation routines. Activation creates storage, tables, capabilities and default options without deleting user data.
 */

declare(strict_types=1);

namespace GrailHr\Shared\Infrastructure\Activation;

use GrailHr\Settings\Infrastructure\SettingsRepository;
use GrailHr\Shared\Infrastructure\Logging\Logger;
use GrailHr\Shared\Infrastructure\Security\CapabilityRegistrar;

final class Activator
{
    public function __construct(private readonly SettingsRepository $settings, private readonly Logger $logger)
    {
    }

    public function activate(): void
    {
        $this->settings->installDefaults();
        (new CapabilityRegistrar())->registerCapabilities();
        $this->createPrivateDirectories();
        $this->createTables();
        $this->scheduleCron();
        flush_rewrite_rules(false);
    }

    public function deactivate(): void
    {
        $this->unscheduleCron();
        $this->revokeTokens();
        flush_rewrite_rules(false);
    }



    private function scheduleCron(): void
    {
        if (!wp_next_scheduled('grail_hr_cleanup_tmp')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'grail_hr_cleanup_tmp');
        }

        foreach (['grail_hr_cleanup_exports', 'grail_hr_cleanup_logs', 'grail_hr_cleanup_expired_tokens'] as $hook) {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', $hook);
            }
        }
    }

    private function unscheduleCron(): void
    {
        foreach (['grail_hr_cleanup_tmp', 'grail_hr_cleanup_exports', 'grail_hr_cleanup_logs', 'grail_hr_cleanup_expired_tokens'] as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }

    private function revokeTokens(): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->usermeta} SET meta_value = %s WHERE meta_key = %s",
            gmdate('Y-m-d H:i:s'),
            'grail_hr_token_revoked_at'
        ));
    }

    private function createPrivateDirectories(): void
    {
        foreach (['logs', 'tmp', 'exports'] as $directory) {
            $path = WP_CONTENT_DIR . '/grail-hr-private/' . $directory;
            wp_mkdir_p($path);
            file_put_contents($path . '/index.php', "<?php\n// Silence is golden.\n", LOCK_EX);
            file_put_contents($path . '/.htaccess', "Deny from all\n", LOCK_EX);
        }
    }

    private function createTables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $wpdb->prefix . 'grail_hr_profile_analyses';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            profile_post_id BIGINT UNSIGNED NOT NULL,
            analysis_uuid CHAR(36) NOT NULL,
            schema_version VARCHAR(20) NOT NULL DEFAULT '1.0',
            analysis_json LONGTEXT NULL,
            candidate_email VARCHAR(190) NULL,
            candidate_phone VARCHAR(80) NULL,
            candidate_location VARCHAR(190) NULL,
            candidate_linkedin_url VARCHAR(255) NULL,
            candidate_portfolio_url VARCHAR(255) NULL,
            primary_job_label VARCHAR(190) NULL,
            primary_job_normalized VARCHAR(190) NULL,
            primary_job_confidence VARCHAR(20) NULL,
            seniority_level VARCHAR(40) NULL,
            seniority_years_estimate SMALLINT UNSIGNED NULL,
            seniority_confidence VARCHAR(20) NULL,
            summary_short VARCHAR(500) NULL,
            review_status VARCHAR(40) NOT NULL DEFAULT 'to_review',
            analysis_status VARCHAR(40) NOT NULL DEFAULT 'none',
            source_file_hash CHAR(64) NULL,
            source_original_extension VARCHAR(20) NULL,
            source_mime_type VARCHAR(100) NULL,
            source_file_size BIGINT UNSIGNED NULL,
            provider VARCHAR(40) NULL,
            model VARCHAR(100) NULL,
            prompt_version VARCHAR(50) NULL,
            warnings_count INT UNSIGNED NOT NULL DEFAULT 0,
            analysis_duration_ms INT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            updated_by BIGINT UNSIGNED NULL,
            validated_by BIGINT UNSIGNED NULL,
            deleted_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            validated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY profile_post_id (profile_post_id),
            KEY candidate_email (candidate_email),
            KEY primary_job_normalized (primary_job_normalized),
            KEY seniority_level (seniority_level),
            KEY review_status (review_status),
            KEY analysis_status (analysis_status),
            KEY source_file_hash (source_file_hash),
            KEY deleted_at (deleted_at)
        ) {$charset};";

        dbDelta($sql);
        update_option('grail_hr_db_version', '1');
        $this->logger->info('activation', 'Grail HR activated.');
    }
}
