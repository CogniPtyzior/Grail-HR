<?php
/**
 * WordPress user-profile integration for Grail HR access.
 *
 * The plugin keeps authentication on native WordPress users. This adapter adds the HR activation fields,
 * assigns per-user capabilities for recruiter/manager levels, exposes useful list columns and revokes tokens
 * whenever access is disabled.
 */

declare(strict_types=1);

namespace GrailHr\IdentityAccess\Infrastructure;

use GrailHr\Shared\Infrastructure\Security\CapabilityRegistrar;
use WP_User;

final class UserAccessManager
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function register(): void
    {
        add_action('show_user_profile', [$this, 'renderFields']);
        add_action('edit_user_profile', [$this, 'renderFields']);
        add_action('personal_options_update', [$this, 'saveFields']);
        add_action('edit_user_profile_update', [$this, 'saveFields']);
        add_filter('manage_users_columns', [$this, 'addColumns']);
        add_filter('manage_users_custom_column', [$this, 'renderColumn'], 10, 3);
        add_action('restrict_manage_users', [$this, 'renderAccessFilter']);
        add_action('pre_get_users', [$this, 'applyAccessFilter']);
    }

    public function renderFields(WP_User $user): void
    {
        if (!current_user_can(CapabilityRegistrar::MANAGE_USERS)) {
            return;
        }

        $active = (bool) get_user_meta($user->ID, 'grail_hr_is_active', true);
        $level = (string) get_user_meta($user->ID, 'grail_hr_access_level', true) ?: 'recruiter';
        $lastUsedAt = (string) get_user_meta($user->ID, 'grail_hr_token_last_used_at', true);
        $expiresAt = (string) get_user_meta($user->ID, 'grail_hr_token_expires_at', true);

        wp_nonce_field('grail_hr_save_user_access', 'grail_hr_user_access_nonce');
        echo '<h2>Accès Grail HR</h2><table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">Activer l’accès</th><td><label>';
        echo '<input type="checkbox" name="grail_hr_is_active" value="1" ' . checked($active, true, false) . '> Accès actif';
        echo '</label></td></tr>';
        echo '<tr><th scope="row"><label for="grail_hr_access_level">Niveau d’accès</label></th><td>';
        echo '<select id="grail_hr_access_level" name="grail_hr_access_level">';
        echo '<option value="recruiter" ' . selected($level, 'recruiter', false) . '>Recruteur</option>';
        echo '<option value="manager" ' . selected($level, 'manager', false) . '>Manager</option>';
        echo '</select></td></tr>';
        echo '<tr><th scope="row">Token</th><td>';
        echo '<p>Expiration : ' . esc_html($expiresAt ?: 'aucun token actif') . '</p>';
        echo '<p>Dernière utilisation : ' . esc_html($lastUsedAt ?: 'jamais') . '</p>';
        echo '<label><input type="checkbox" name="grail_hr_revoke_token" value="1"> Révoquer le token actuel</label>';
        echo '</td></tr></tbody></table>';
    }

    public function saveFields(int $userId): void
    {
        if (!current_user_can(CapabilityRegistrar::MANAGE_USERS)) {
            return;
        }

        $nonce = (string) ($_POST['grail_hr_user_access_nonce'] ?? '');

        if (!wp_verify_nonce($nonce, 'grail_hr_save_user_access')) {
            return;
        }

        $active = !empty($_POST['grail_hr_is_active']);
        $level = sanitize_key((string) ($_POST['grail_hr_access_level'] ?? 'recruiter'));
        $level = in_array($level, ['recruiter', 'manager'], true) ? $level : 'recruiter';
        $user = new WP_User($userId);

        update_user_meta($userId, 'grail_hr_is_active', $active ? '1' : '0');
        update_user_meta($userId, 'grail_hr_access_level', $level);
        $this->removeHrCaps($user);

        if ($active) {
            foreach ($this->capsForLevel($level) as $capability) {
                $user->add_cap($capability);
            }
        } else {
            $this->tokens->revoke($userId);
        }

        if (!empty($_POST['grail_hr_revoke_token'])) {
            $this->tokens->revoke($userId);
        }
    }

    /** @param array<string, string> $columns @return array<string, string> */
    public function addColumns(array $columns): array
    {
        $columns['grail_hr_access'] = 'Accès Grail HR';
        $columns['grail_hr_level'] = 'Niveau Grail HR';
        $columns['grail_hr_token'] = 'Token Grail HR';

        return $columns;
    }


    public function renderAccessFilter(string $which): void
    {
        if ($which !== 'top' || !current_user_can(CapabilityRegistrar::MANAGE_USERS)) {
            return;
        }

        $current = sanitize_key((string) ($_GET['grail_hr_access_filter'] ?? ''));
        echo '<label class="screen-reader-text" for="grail_hr_access_filter">Filtrer par accès Grail HR</label>';
        echo '<select id="grail_hr_access_filter" name="grail_hr_access_filter">';
        echo '<option value="" ' . selected($current, '', false) . '>Tous les accès Grail HR</option>';
        echo '<option value="active" ' . selected($current, 'active', false) . '>Accès actif</option>';
        echo '<option value="recruiter" ' . selected($current, 'recruiter', false) . '>Recruteur Grail HR</option>';
        echo '<option value="manager" ' . selected($current, 'manager', false) . '>Manager Grail HR</option>';
        echo '<option value="inactive" ' . selected($current, 'inactive', false) . '>Sans accès actif</option>';
        echo '</select>';
    }

    public function applyAccessFilter(\WP_User_Query $query): void
    {
        global $pagenow;

        if (!is_admin() || $pagenow !== 'users.php' || !current_user_can(CapabilityRegistrar::MANAGE_USERS)) {
            return;
        }

        $filter = sanitize_key((string) ($_GET['grail_hr_access_filter'] ?? ''));

        if ($filter === '') {
            return;
        }

        $metaQuery = (array) $query->get('meta_query');

        if ($filter === 'inactive') {
            $metaQuery[] = [
                'relation' => 'OR',
                ['key' => 'grail_hr_is_active', 'compare' => 'NOT EXISTS'],
                ['key' => 'grail_hr_is_active', 'value' => '1', 'compare' => '!='],
            ];
        } else {
            $metaQuery[] = ['key' => 'grail_hr_is_active', 'value' => '1'];

            if (in_array($filter, ['recruiter', 'manager'], true)) {
                $metaQuery[] = ['key' => 'grail_hr_access_level', 'value' => $filter];
            }
        }

        $query->set('meta_query', $metaQuery);
    }

    public function renderColumn(string $output, string $column, int $userId): string
    {
        if ($column === 'grail_hr_access') {
            return get_user_meta($userId, 'grail_hr_is_active', true) ? 'Actif' : 'Inactif';
        }

        if ($column === 'grail_hr_level') {
            $level = (string) get_user_meta($userId, 'grail_hr_access_level', true);

            return $level === 'manager' ? 'Manager' : 'Recruteur';
        }

        if ($column === 'grail_hr_token') {
            $lastUsedAt = (string) get_user_meta($userId, 'grail_hr_token_last_used_at', true);

            return $lastUsedAt !== '' ? 'Dernière utilisation : ' . esc_html($lastUsedAt) : 'Aucune utilisation';
        }

        return $output;
    }

    /** @return list<string> */
    private function capsForLevel(string $level): array
    {
        $recruiter = [
            CapabilityRegistrar::USE_APP,
            CapabilityRegistrar::ANALYZE_CV,
            CapabilityRegistrar::VIEW_PROFILES,
            CapabilityRegistrar::EDIT_PROFILES,
            CapabilityRegistrar::VALIDATE_PROFILES,
            CapabilityRegistrar::ARCHIVE_PROFILES,
            CapabilityRegistrar::EXPORT_DATA,
        ];

        if ($level === 'manager') {
            return array_merge($recruiter, [
                CapabilityRegistrar::DELETE_PROFILES,
                CapabilityRegistrar::MANAGE_PROFILES,
                CapabilityRegistrar::VIEW_LOGS,
            ]);
        }

        return $recruiter;
    }

    private function removeHrCaps(WP_User $user): void
    {
        foreach (CapabilityRegistrar::all() as $capability) {
            $user->remove_cap($capability);
        }
    }
}
