<?php
/**
 * Opaque bearer token service. Only token hashes are persisted in user_meta.
 */

declare(strict_types=1);

namespace GrailHr\IdentityAccess\Infrastructure;

use WP_User;

final class TokenService
{
    private const HASH = 'grail_hr_token_hash';
    private const CREATED_AT = 'grail_hr_token_created_at';
    private const EXPIRES_AT = 'grail_hr_token_expires_at';
    private const LAST_USED_AT = 'grail_hr_token_last_used_at';
    private const REVOKED_AT = 'grail_hr_token_revoked_at';

    /** @return array{token: string, expires_at: string} */
    public function issue(WP_User $user): array
    {
        $token = bin2hex(random_bytes(32));
        $now = gmdate('Y-m-d H:i:s');
        $expiresAt = gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS);

        update_user_meta($user->ID, self::HASH, hash('sha256', $token));
        update_user_meta($user->ID, self::CREATED_AT, $now);
        update_user_meta($user->ID, self::EXPIRES_AT, $expiresAt);
        update_user_meta($user->ID, self::LAST_USED_AT, '');
        update_user_meta($user->ID, self::REVOKED_AT, '');

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    public function revoke(int $userId): void
    {
        update_user_meta($userId, self::REVOKED_AT, gmdate('Y-m-d H:i:s'));
    }

    public function revokeBearer(string $token): void
    {
        $user = $this->userFromBearer($token);

        if ($user instanceof WP_User) {
            $this->revoke($user->ID);
        }
    }

    public function userFromBearer(string $token): ?WP_User
    {
        $hash = hash('sha256', $token);
        $users = get_users([
            'meta_key' => self::HASH,
            'meta_value' => $hash,
            'number' => 1,
            'fields' => 'all',
        ]);

        if (!$users || !$users[0] instanceof WP_User) {
            return null;
        }

        $user = $users[0];
        $expiresAt = (string) get_user_meta($user->ID, self::EXPIRES_AT, true);
        $revokedAt = (string) get_user_meta($user->ID, self::REVOKED_AT, true);

        if ($revokedAt !== '' || strtotime($expiresAt) < time()) {
            return null;
        }

        update_user_meta($user->ID, self::LAST_USED_AT, gmdate('Y-m-d H:i:s'));

        return $user;
    }
}
