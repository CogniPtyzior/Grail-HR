<?php
/**
 * Application service for profile creation, access-controlled reads, update, validation and deletion.
 *
 * Controllers deliberately delegate ownership checks to this layer so every profile mutation follows the same rule:
 * recruiters work on their own profiles, while managers/admins can work on all profiles.
 */

declare(strict_types=1);

namespace GrailHr\ProfileManagement\Application;

use GrailHr\ProfileAnalysis\Application\AnalysisNormalizer;
use GrailHr\ProfileAnalysis\Application\AnalysisValidator;
use GrailHr\ProfileManagement\Infrastructure\ProfileRepository;
use GrailHr\Shared\Domain\Exception\PermissionDeniedException;
use GrailHr\Shared\Infrastructure\Logging\Logger;

final class ProfileService
{
    public function __construct(
        private readonly ProfileRepository $profiles,
        private readonly AnalysisValidator $validator,
        private readonly AnalysisNormalizer $normalizer,
        private readonly Logger $logger
    ) {
    }

    /** @param array<string, mixed> $input */
    public function createManual(array $input, int $userId): int
    {
        $title = sanitize_text_field((string) ($input['title'] ?? ''));
        $title = $title !== '' ? $title : 'Profil CV du ' . wp_date('d/m/Y');

        $postId = $this->profiles->createPost([
            'title' => $title,
            'short_summary' => sanitize_textarea_field((string) ($input['short_summary'] ?? '')),
            'long_summary' => sanitize_textarea_field((string) ($input['long_summary'] ?? '')),
        ], $userId);

        $analysis = $this->normalizer->emptyAnalysis();
        $analysis['summary']['profile_title'] = $title;
        $analysis['summary']['short_summary'] = sanitize_textarea_field((string) ($input['short_summary'] ?? ''));
        $analysis['summary']['long_summary'] = sanitize_textarea_field((string) ($input['long_summary'] ?? ''));
        $analysis['candidate']['email'] = sanitize_email((string) ($input['email'] ?? ''));
        $analysis['candidate']['phone'] = sanitize_text_field((string) ($input['phone'] ?? ''));
        $analysis['candidate']['location'] = sanitize_text_field((string) ($input['location'] ?? ''));
        $analysis['career_targeting']['primary_job']['label'] = sanitize_text_field((string) ($input['primary_job'] ?? ''));
        $analysis['career_targeting']['primary_job']['normalized_label'] = sanitize_title((string) ($input['primary_job'] ?? ''));
        $seniority = sanitize_key((string) ($input['seniority'] ?? 'unknown'));
        $allowedSeniority = ['junior', 'confirmed', 'senior', 'lead', 'manager', 'expert', 'unknown'];
        $analysis['seniority']['level'] = in_array($seniority, $allowedSeniority, true) ? $seniority : 'unknown';

        $this->saveAnalysis($postId, $analysis, $userId, 'none');
        $this->logger->info('profile_management', 'Manual profile created.', ['profile_id' => $postId, 'user_id' => $userId]);

        return $postId;
    }

    public function createShellForCv(int $userId, string $title = ''): int
    {
        $title = sanitize_text_field($title);
        $title = $title !== '' ? $title : 'Profil CV du ' . wp_date('d/m/Y');
        $postId = $this->profiles->createPost(['title' => $title], $userId);
        $this->logger->info('profile_management', 'Profile shell created before CV analysis.', ['profile_id' => $postId]);

        return $postId;
    }

    /** @param array<string, mixed> $analysis */
    public function saveAnalysis(
        int $postId,
        array $analysis,
        int $userId,
        string $status = 'completed',
        array $extraFields = [],
        ?string $reviewStatus = null
    ): void {
        $analysis = $this->normalizer->normalize($analysis);
        $this->validator->validate($analysis);
        $fields = array_merge($this->normalizer->indexedFields($analysis), $extraFields);

        $this->profiles->saveAnalysis($postId, $analysis, $fields, $userId, $status, $reviewStatus);
    }

    public function markAnalysisStatus(int $postId, int $userId, string $analysisStatus): void
    {
        $this->profiles->updateStatuses($postId, sanitize_key($analysisStatus), 'to_review', $userId);
    }

    public function markAnalysisError(int $postId, int $userId): void
    {
        $this->profiles->markAnalysisError($postId, $userId);
    }

    /** @return array<string, mixed> */
    public function get(int $postId, int $userId, bool $canSeeAll): array
    {
        $this->assertCanAccess($postId, $userId, $canSeeAll);
        $profile = $this->profiles->find($postId);
        $row = $profile['analysis'];
        $analysis = json_decode((string) ($row['analysis_json'] ?? ''), true);

        return [
            'id' => $postId,
            'title' => get_the_title($postId),
            'analysis' => is_array($analysis) ? $analysis : $this->normalizer->emptyAnalysis(),
            'review_status' => (string) $row['review_status'],
            'analysis_status' => (string) $row['analysis_status'],
            'permissions' => [
                'can_edit' => current_user_can('grail_hr_edit_profiles'),
                'can_validate' => current_user_can('grail_hr_validate_profiles'),
                'can_archive' => current_user_can('grail_hr_archive_profiles'),
                'can_delete' => current_user_can('grail_hr_delete_profiles'),
                'can_replace_analysis' => current_user_can('grail_hr_analyze_cv'),
            ],
        ];
    }

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function list(array $query, int $userId, bool $canSeeAll): array
    {
        return $this->profiles->search($query, $userId, $canSeeAll);
    }

    /** @param array<string, mixed> $analysis */
    public function updateAnalysisForUser(int $postId, array $analysis, int $userId, bool $canSeeAll): void
    {
        $this->assertCanAccess($postId, $userId, $canSeeAll);
        $this->saveAnalysis($postId, $analysis, $userId, 'completed', [], 'edited');
    }

    public function assertCanAccess(int $postId, int $userId, bool $canSeeAll): void
    {
        if (!$this->profiles->userCanAccess($postId, $userId, $canSeeAll)) {
            throw new PermissionDeniedException('Vous ne disposez pas des droits nécessaires.');
        }
    }

    public function validate(int $postId, int $userId, bool $canSeeAll): void
    {
        $this->assertCanAccess($postId, $userId, $canSeeAll);
        $this->profiles->updateStatuses($postId, null, 'validated', $userId);
    }

    public function archive(int $postId, int $userId, bool $canSeeAll): void
    {
        $this->assertCanAccess($postId, $userId, $canSeeAll);
        $this->profiles->updateStatuses($postId, null, 'archived', $userId);
    }

    public function reopen(int $postId, int $userId, bool $canSeeAll): void
    {
        $this->assertCanAccess($postId, $userId, $canSeeAll);
        $this->profiles->updateStatuses($postId, null, 'edited', $userId);
    }


    public function restore(int $postId, int $userId, bool $canSeeAll): void
    {
        if (!$this->profiles->userCanRestore($postId, $userId, $canSeeAll)) {
            throw new PermissionDeniedException('Vous ne disposez pas des droits nécessaires.');
        }

        $this->profiles->restore($postId, $userId);
    }

    public function delete(int $postId, int $userId, bool $canSeeAll): void
    {
        $this->assertCanAccess($postId, $userId, $canSeeAll);
        $this->profiles->softDelete($postId, $userId);
    }
}
