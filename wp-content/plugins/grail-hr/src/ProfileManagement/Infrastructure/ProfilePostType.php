<?php
/**
 * Registers the private, administrable WordPress CPT used as profile anchor.
 *
 * Business data lives in the custom analysis table. The CPT provides WordPress admin visibility, ownership and a
 * searchable title/content/excerpt projection synchronized from the current analysis JSON.
 */

declare(strict_types=1);

namespace GrailHr\ProfileManagement\Infrastructure;

use GrailHr\Shared\Infrastructure\Security\CapabilityRegistrar;

final class ProfilePostType
{
    public const POST_TYPE = 'grail_hr_profile';

    public function register(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'Profils CV',
                'singular_name' => 'Profil CV',
                'add_new_item' => 'Ajouter un profil CV',
                'edit_item' => 'Modifier le profil CV',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'grail-hr',
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'excerpt', 'author'],
            'map_meta_cap' => false,
            'capabilities' => [
                'edit_post' => CapabilityRegistrar::EDIT_PROFILES,
                'read_post' => CapabilityRegistrar::VIEW_PROFILES,
                'delete_post' => CapabilityRegistrar::DELETE_PROFILES,
                'edit_posts' => CapabilityRegistrar::VIEW_PROFILES,
                'edit_others_posts' => CapabilityRegistrar::MANAGE_PROFILES,
                'delete_posts' => CapabilityRegistrar::DELETE_PROFILES,
                'delete_others_posts' => CapabilityRegistrar::DELETE_PROFILES,
                'publish_posts' => CapabilityRegistrar::EDIT_PROFILES,
                'read_private_posts' => CapabilityRegistrar::VIEW_PROFILES,
                'create_posts' => CapabilityRegistrar::EDIT_PROFILES,
            ],
        ]);
    }
}
