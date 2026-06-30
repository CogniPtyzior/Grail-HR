<?php
/**
 * REST authentication helper used by controllers to enforce bearer token, active user and capabilities.
 */

declare(strict_types=1);

namespace GrailHr\IdentityAccess\Infrastructure;

use GrailHr\Shared\Domain\Exception\PermissionDeniedException;
use GrailHr\Shared\Domain\Exception\UnauthorizedException;
use WP_REST_Request;
use WP_User;

final class AuthMiddleware
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function requireUser(WP_REST_Request $request, ?string $capability = null): WP_User
    {
        $header = (string) $request->get_header('authorization');

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            throw new UnauthorizedException();
        }

        $user = $this->tokens->userFromBearer(trim($matches[1]));

        if (!$user instanceof WP_User) {
            throw new UnauthorizedException();
        }

        $isActive = (bool) get_user_meta($user->ID, 'grail_hr_is_active', true) || user_can($user, 'manage_options');

        if (!$isActive) {
            throw new PermissionDeniedException('Votre accès Grail HR n’est pas actif. Contactez l’administrateur du site.');
        }

        wp_set_current_user($user->ID);

        if ($capability !== null && !user_can($user, $capability)) {
            throw new PermissionDeniedException();
        }

        return $user;
    }
}
