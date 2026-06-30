<?php
/**
 * Plugin Name: Grail HR
 * Description: Application interne d’analyse de CV assistée par IA pour créer, relire, valider, archiver et exporter des profils candidats.
 * Version: 0.1.0
 * Requires PHP: 8.3
 * Requires at least: 6.4
 * Author: Dynamics Spirit
 * Text Domain: grail-hr
 * Domain Path: /languages
 *
 * This bootstrap file is deliberately small. It loads Composer when available, falls back to a tiny PSR-4 autoloader in development,
 * defines stable plugin constants, and delegates all WordPress integration to the Plugin class.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('GRAIL_HR_VERSION', '0.1.0');
define('GRAIL_HR_PLUGIN_FILE', __FILE__);
define('GRAIL_HR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GRAIL_HR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GRAIL_HR_TEXT_DOMAIN', 'grail-hr');

$grailHrAutoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($grailHrAutoload)) {
    require_once $grailHrAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'GrailHr\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_readable($file)) {
            require_once $file;
        }
    });
}

GrailHr\Plugin::boot(__FILE__);
