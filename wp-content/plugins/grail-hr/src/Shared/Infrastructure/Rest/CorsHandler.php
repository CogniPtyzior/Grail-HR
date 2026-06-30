<?php
/**
 * Development-only CORS handler used when Nuxt runs on localhost and WordPress runs in DDEV.
 */

declare(strict_types=1);

namespace GrailHr\Shared\Infrastructure\Rest;

use GrailHr\Settings\Infrastructure\SettingsRepository;

final class CorsHandler
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function register(): void
    {
        add_filter('rest_pre_serve_request', [$this, 'sendDevCorsHeaders'], 10, 4);
    }

    public function sendDevCorsHeaders(bool $served): bool
    {
        if (!$this->settings->isDevCorsEnabled()) {
            return $served;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = ['http://localhost:3000', 'http://127.0.0.1:3000'];

        if (in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: false');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        }

        return $served;
    }
}
