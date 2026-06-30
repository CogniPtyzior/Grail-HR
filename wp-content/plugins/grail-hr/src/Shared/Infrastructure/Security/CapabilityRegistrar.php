<?php
/**
 * Registers Grail HR capabilities on administrator roles. HR access for non-admin users is managed per user.
 */

declare(strict_types=1);

namespace GrailHr\Shared\Infrastructure\Security;

final class CapabilityRegistrar
{
    public const USE_APP = 'grail_hr_use_app';
    public const ANALYZE_CV = 'grail_hr_analyze_cv';
    public const VIEW_PROFILES = 'grail_hr_view_profiles';
    public const EDIT_PROFILES = 'grail_hr_edit_profiles';
    public const VALIDATE_PROFILES = 'grail_hr_validate_profiles';
    public const ARCHIVE_PROFILES = 'grail_hr_archive_profiles';
    public const DELETE_PROFILES = 'grail_hr_delete_profiles';
    public const MANAGE_PROFILES = 'grail_hr_manage_profiles';
    public const MANAGE_SETTINGS = 'grail_hr_manage_settings';
    public const MANAGE_USERS = 'grail_hr_manage_users';
    public const EXPORT_DATA = 'grail_hr_export_data';
    public const VIEW_LOGS = 'grail_hr_view_logs';
    public const MANAGE_LOGS = 'grail_hr_manage_logs';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::USE_APP,
            self::ANALYZE_CV,
            self::VIEW_PROFILES,
            self::EDIT_PROFILES,
            self::VALIDATE_PROFILES,
            self::ARCHIVE_PROFILES,
            self::DELETE_PROFILES,
            self::MANAGE_PROFILES,
            self::MANAGE_SETTINGS,
            self::MANAGE_USERS,
            self::EXPORT_DATA,
            self::VIEW_LOGS,
            self::MANAGE_LOGS,
        ];
    }

    public function registerCapabilities(): void
    {
        $admin = get_role('administrator');

        if (!$admin) {
            return;
        }

        foreach (self::all() as $capability) {
            $admin->add_cap($capability);
        }
    }
}
