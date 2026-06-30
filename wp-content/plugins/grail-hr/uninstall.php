<?php
/**
 * Uninstall hook. Data is intentionally kept by default to avoid accidental personal-data loss.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Full purge can be added later behind an explicit double-confirmed option.
