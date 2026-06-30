<?php
/**
 * REST controller for authentication endpoints.
 */

declare(strict_types=1);

namespace GrailHr\IdentityAccess\Presentation;

use GrailHr\IdentityAccess\Application\AuthService;
use GrailHr\Shared\Infrastructure\Rest\RestErrorMapper;
use WP_REST_Request;

final class AuthController
{
    public function __construct(private readonly AuthService $auth, private readonly RestErrorMapper $errors)
    {
    }

    public function registerRoutes(): void
    {
        register_rest_route('grail-hr/v1', '/auth/login', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'login'],
        ]);

        register_rest_route('grail-hr/v1', '/auth/logout', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'logout'],
        ]);

        register_rest_route('grail-hr/v1', '/me', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [$this, 'me'],
        ]);
    }


    public function me(WP_REST_Request $request): mixed
    {
        try {
            $header = (string) $request->get_header('authorization');

            if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
                throw new \GrailHr\Shared\Domain\Exception\UnauthorizedException();
            }

            return rest_ensure_response($this->auth->me(trim($matches[1])));
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }

    public function login(WP_REST_Request $request): mixed
    {
        try {
            return rest_ensure_response($this->auth->login(
                sanitize_text_field((string) $request->get_param('login')),
                (string) $request->get_param('password')
            ));
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }

    public function logout(WP_REST_Request $request): mixed
    {
        try {
            $header = (string) $request->get_header('authorization');

            if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
                $this->auth->logoutByToken(trim($matches[1]));
            }

            return rest_ensure_response(['ok' => true]);
        } catch (\Throwable $exception) {
            return $this->errors->map($exception);
        }
    }
}
