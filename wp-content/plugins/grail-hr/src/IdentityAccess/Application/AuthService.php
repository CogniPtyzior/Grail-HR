<?php
/**
 * Application service for login/logout. It verifies WordPress credentials and Grail HR access before issuing a token.
 */

declare(strict_types=1);

namespace GrailHr\IdentityAccess\Application;

use GrailHr\IdentityAccess\Infrastructure\TokenService;
use GrailHr\Shared\Domain\Exception\PermissionDeniedException;
use GrailHr\Shared\Domain\Exception\UnauthorizedException;
use GrailHr\Shared\Infrastructure\Security\CapabilityRegistrar;
use WP_Error;

final class AuthService
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    /** @return array<string, mixed> */
    public function login(string $login, string $password): array
    {
        $user = wp_authenticate($login, $password);

        if ($user instanceof WP_Error) {
            throw new UnauthorizedException('Identifiants invalides.');
        }

        $isActive = (bool) get_user_meta($user->ID, 'grail_hr_is_active', true) || user_can($user, 'manage_options');

        if (!$isActive || !user_can($user, CapabilityRegistrar::USE_APP)) {
            throw new PermissionDeniedException('Votre accès Grail HR n’est pas actif. Contactez l’administrateur du site.');
        }

        $token = $this->tokens->issue($user);

        return [
            'token' => $token['token'],
            'expires_at' => $token['expires_at'],
            'user' => [
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'capabilities' => array_values(array_filter(CapabilityRegistrar::all(), static fn (string $cap): bool => user_can($user, $cap))),
            ],
        ];
    }


    /** @return array<string, mixed> */
    public function me(string $token): array
    {
        $user = $this->tokens->userFromBearer($token);

        if (!$user instanceof \WP_User) {
            throw new UnauthorizedException();
        }

        $isActive = (bool) get_user_meta($user->ID, 'grail_hr_is_active', true) || user_can($user, 'manage_options');

        if (!$isActive || !user_can($user, CapabilityRegistrar::USE_APP)) {
            throw new PermissionDeniedException('Votre accès Grail HR n’est pas actif. Contactez l’administrateur du site.');
        }

        return [
            'id' => $user->ID,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'capabilities' => array_values(array_filter(CapabilityRegistrar::all(), static fn (string $cap): bool => user_can($user, $cap))),
        ];
    }

    public function logoutByToken(string $token): void
    {
        $this->tokens->revokeBearer($token);
    }
}
