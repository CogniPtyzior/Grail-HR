<?php
/**
 * PHPUnit bootstrap for pure unit tests.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($value) {
        return filter_var((string) $value, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($value) {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value) ?: '');
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($value) {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $value), '-'));
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($value) {
        return filter_var((string) $value, FILTER_SANITIZE_URL) ?: '';
    }
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $grailHrTestOptions;

        return $grailHrTestOptions[$option] ?? $default;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        global $grailHrTestTransients;

        return $grailHrTestTransients[$transient]['value'] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        global $grailHrTestTransients;

        $grailHrTestTransients[$transient] = [
            'value' => $value,
            'expiration' => $expiration,
        ];

        return true;
    }
}
