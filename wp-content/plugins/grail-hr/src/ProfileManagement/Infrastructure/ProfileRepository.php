<?php
/**
 * Persistence adapter for profile CPT and custom analysis table.
 */

declare(strict_types=1);

namespace GrailHr\ProfileManagement\Infrastructure;

use GrailHr\Settings\Infrastructure\SettingsRepository;
use GrailHr\Shared\Domain\Exception\NotFoundException;
use GrailHr\Shared\Infrastructure\Logging\Logger;
use wpdb;

final class ProfileRepository
{
    public function __construct(private readonly SettingsRepository $settings, private readonly Logger $logger)
    {
    }

    /** @param array<string, mixed> $data */
    public function createPost(array $data, int $userId): int
    {
        $postId = wp_insert_post([
            'post_type' => ProfilePostType::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => sanitize_text_field((string) ($data['title'] ?? 'Profil CV du ' . wp_date('d/m/Y'))),
            'post_content' => wp_kses_post((string) ($data['long_summary'] ?? '')),
            'post_excerpt' => sanitize_textarea_field((string) ($data['short_summary'] ?? '')),
            'post_author' => $userId,
        ], true);

        if (is_wp_error($postId)) {
            throw new NotFoundException('Impossible de créer le profil.');
        }

        $this->createAnalysisRow((int) $postId, $userId);

        return (int) $postId;
    }

    public function createAnalysisRow(int $postId, int $userId): void
    {
        global $wpdb;

        $wpdb->replace($this->table(), [
            'profile_post_id' => $postId,
            'analysis_uuid' => wp_generate_uuid4(),
            'schema_version' => '1.0',
            'analysis_json' => null,
            'analysis_status' => 'none',
            'review_status' => 'edited',
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true),
        ]);
    }

    /** @param array<string, mixed> $fields @param array<string, mixed> $analysis */
    public function saveAnalysis(
        int $postId,
        array $analysis,
        array $fields,
        int $userId,
        string $analysisStatus = 'completed',
        ?string $reviewStatus = null
    ): void {
        global $wpdb;

        $data = array_merge($fields, [
            'analysis_json' => wp_json_encode($analysis),
            'analysis_status' => sanitize_key($analysisStatus),
            'review_status' => $reviewStatus ?: ($analysisStatus === 'completed' ? 'to_review' : 'edited'),
            'updated_by' => $userId,
            'updated_at' => current_time('mysql', true),
        ]);

        $wpdb->update($this->table(), $data, ['profile_post_id' => $postId]);
        $this->syncPostFromAnalysis($postId, $analysis);
    }

    public function markAnalysisError(int $postId, int $userId): void
    {
        global $wpdb;

        $wpdb->update($this->table(), [
            'analysis_status' => 'error',
            'review_status' => 'to_review',
            'updated_by' => $userId,
            'updated_at' => current_time('mysql', true),
        ], ['profile_post_id' => $postId]);
    }

    /** @return array<string, mixed> */
    public function find(int $postId): array
    {
        global $wpdb;

        $post = get_post($postId);

        if (!$post || $post->post_type !== ProfilePostType::POST_TYPE) {
            throw new NotFoundException('Profil introuvable.');
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table()} WHERE profile_post_id = %d", $postId), ARRAY_A);

        if (!$row || $row['deleted_at'] !== null) {
            throw new NotFoundException('Profil introuvable.');
        }

        return ['post' => $post, 'analysis' => $row];
    }


    public function userCanAccess(int $postId, int $userId, bool $canSeeAll): bool
    {
        global $wpdb;

        $post = get_post($postId);

        if (!$post || $post->post_type !== ProfilePostType::POST_TYPE) {
            return false;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT deleted_at FROM {$this->table()} WHERE profile_post_id = %d",
            $postId
        ), ARRAY_A);

        if (!$row || $row['deleted_at'] !== null) {
            return false;
        }

        return $canSeeAll || (int) $post->post_author === $userId;
    }

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function search(array $query, int $userId, bool $canSeeAll): array
    {
        global $wpdb;

        $page = max(1, absint($query['page'] ?? 1));
        $perPage = min(100, max(10, absint($query['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        $where = ["p.post_type = %s", "p.post_status != 'trash'", "a.deleted_at IS NULL"];
        $params = [ProfilePostType::POST_TYPE];

        if (!$canSeeAll) {
            $where[] = 'p.post_author = %d';
            $params[] = $userId;
        }

        foreach (['review_status', 'analysis_status', 'seniority_level'] as $filter) {
            if (!empty($query[$filter])) {
                $where[] = "a.{$filter} = %s";
                $params[] = sanitize_key((string) $query[$filter]);
            }
        }

        if (!empty($query['primary_job'])) {
            $where[] = 'a.primary_job_normalized = %s';
            $params[] = sanitize_title((string) $query['primary_job']);
        }

        if ($canSeeAll && !empty($query['created_by'])) {
            $where[] = 'p.post_author = %d';
            $params[] = absint($query['created_by']);
        }

        if (!empty($query['date_from'])) {
            $where[] = 'a.created_at >= %s';
            $params[] = sanitize_text_field((string) $query['date_from']) . ' 00:00:00';
        }

        if (!empty($query['date_to'])) {
            $where[] = 'a.created_at <= %s';
            $params[] = sanitize_text_field((string) $query['date_to']) . ' 23:59:59';
        }

        $search = trim((string) ($query['search'] ?? ''));

        if (mb_strlen($search) >= 2) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(p.post_title LIKE %s OR p.post_content LIKE %s OR p.post_excerpt LIKE %s OR a.candidate_email LIKE %s '
                . 'OR a.candidate_phone LIKE %s OR a.candidate_location LIKE %s OR a.primary_job_label LIKE %s OR a.summary_short LIKE %s)';
            array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
        }

        $whereSql = implode(' AND ', $where);
        $allowedSorts = ['updated_at', 'created_at', 'review_status', 'seniority_level', 'primary_job_label'];
        $orderBy = in_array((string) ($query['sort_by'] ?? ''), $allowedSorts, true) ? (string) $query['sort_by'] : 'updated_at';
        $direction = strtolower((string) ($query['sort_order'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->posts} p INNER JOIN {$this->table()} a ON a.profile_post_id = p.ID
             WHERE {$whereSql}",
            $params
        ));
        $sqlParams = array_merge($params, [$perPage, $offset]);

        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_excerpt, p.post_author, a.*
             FROM {$wpdb->posts} p INNER JOIN {$this->table()} a ON a.profile_post_id = p.ID
             WHERE {$whereSql} ORDER BY a.{$orderBy} {$direction} LIMIT %d OFFSET %d",
            $sqlParams
        ), ARRAY_A);

        return [
            'data' => array_map([$this, 'mapListRow'], $items ?: []),
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)],
        ];
    }


    /** @return list<array<string, mixed>> */
    public function exportRows(bool $includeArchived = true): array
    {
        global $wpdb;

        $where = "p.post_type = %s AND p.post_status != 'trash' AND a.deleted_at IS NULL";
        $params = [ProfilePostType::POST_TYPE];

        if (!$includeArchived) {
            $where .= " AND a.review_status != %s";
            $params[] = 'archived';
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_content, p.post_excerpt, p.post_author, a.*
             FROM {$wpdb->posts} p INNER JOIN {$this->table()} a ON a.profile_post_id = p.ID
             WHERE {$where} ORDER BY a.updated_at DESC",
            $params
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function updateStatuses(int $postId, ?string $analysisStatus, string $reviewStatus, int $userId): void
    {
        global $wpdb;

        $data = [
            'review_status' => sanitize_key($reviewStatus),
            'updated_by' => $userId,
            'updated_at' => current_time('mysql', true),
        ];

        if ($analysisStatus !== null) {
            $data['analysis_status'] = sanitize_key($analysisStatus);
        }

        if ($reviewStatus === 'validated') {
            $data['validated_by'] = $userId;
            $data['validated_at'] = current_time('mysql', true);
        }

        $wpdb->update($this->table(), $data, ['profile_post_id' => $postId]);
    }

    public function softDelete(int $postId, int $userId): void
    {
        global $wpdb;

        wp_trash_post($postId);
        $wpdb->update($this->table(), [
            'deleted_by' => $userId,
            'deleted_at' => current_time('mysql', true),
            'updated_by' => $userId,
            'updated_at' => current_time('mysql', true),
        ], ['profile_post_id' => $postId]);
    }


    public function restore(int $postId, int $userId): void
    {
        global $wpdb;

        wp_untrash_post($postId);
        $wpdb->update($this->table(), [
            'deleted_by' => null,
            'deleted_at' => null,
            'updated_by' => $userId,
            'updated_at' => current_time('mysql', true),
        ], ['profile_post_id' => $postId]);
    }

    public function userCanRestore(int $postId, int $userId, bool $canSeeAll): bool
    {
        $post = get_post($postId);

        return $post && $post->post_type === ProfilePostType::POST_TYPE && ($canSeeAll || (int) $post->post_author === $userId);
    }

    private function syncPostFromAnalysis(int $postId, array $analysis): void
    {
        $candidate = (array) ($analysis['candidate'] ?? []);
        $summary = (array) ($analysis['summary'] ?? []);
        $title = (string) ($candidate['full_name'] ?? '') ?: (string) ($summary['profile_title'] ?? '') ?: 'Profil CV du ' . wp_date('d/m/Y');

        wp_update_post([
            'ID' => $postId,
            'post_title' => sanitize_text_field($title),
            'post_content' => wp_kses_post((string) ($summary['long_summary'] ?? '')),
            'post_excerpt' => sanitize_textarea_field((string) ($summary['short_summary'] ?? '')),
        ]);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function mapListRow(array $row): array
    {
        $analysis = json_decode((string) ($row['analysis_json'] ?? ''), true);
        $analysis = is_array($analysis) ? $analysis : [];
        $candidate = (array) ($analysis['candidate'] ?? []);
        $summary = (array) ($analysis['summary'] ?? []);

        return [
            'id' => (int) $row['profile_post_id'],
            'title' => (string) $row['post_title'],
            'profile_title' => (string) ($summary['profile_title'] ?? ''),
            'candidate_full_name' => (string) ($candidate['full_name'] ?? ''),
            'summary_short' => (string) ($row['summary_short'] ?: $row['post_excerpt']),
            'candidate_email' => (string) ($row['candidate_email'] ?? ''),
            'candidate_phone' => (string) ($row['candidate_phone'] ?? ''),
            'candidate_location' => (string) ($row['candidate_location'] ?? ''),
            'primary_job_label' => (string) ($row['primary_job_label'] ?? ''),
            'primary_job_confidence' => (string) ($row['primary_job_confidence'] ?? ''),
            'seniority_level' => (string) ($row['seniority_level'] ?? ''),
            'seniority_years_estimate' => $row['seniority_years_estimate'] !== null ? (int) $row['seniority_years_estimate'] : null,
            'review_status' => (string) $row['review_status'],
            'analysis_status' => (string) $row['analysis_status'],
            'warnings_count' => (int) ($row['warnings_count'] ?? 0),
            'created_by' => ['id' => (int) $row['post_author'], 'display_name' => get_the_author_meta('display_name', (int) $row['post_author'])],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    private function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'grail_hr_profile_analyses';
    }
}
