<?php
/**
 * Normalizes analysis payloads, applies safe length bounds and extracts indexed fields.
 */

declare(strict_types=1);

namespace GrailHr\ProfileAnalysis\Application;

final class AnalysisNormalizer
{
    /** @param array<string, mixed> $analysis @return array<string, mixed> */
    public function normalize(array $analysis): array
    {
        $analysis = array_merge($this->emptyAnalysis(), $analysis);
        $analysis['candidate'] = array_merge($this->emptyAnalysis()['candidate'], (array) $analysis['candidate']);
        $analysis['summary'] = array_merge($this->emptyAnalysis()['summary'], (array) $analysis['summary']);
        $analysis['seniority'] = array_merge($this->emptyAnalysis()['seniority'], (array) $analysis['seniority']);
        $analysis['career_targeting'] = array_merge($this->emptyAnalysis()['career_targeting'], (array) $analysis['career_targeting']);

        $analysis['summary']['profile_title'] = $this->text($analysis['summary']['profile_title'], 190);
        $analysis['summary']['short_summary'] = $this->text($analysis['summary']['short_summary'], 350);
        $analysis['summary']['long_summary'] = $this->text($analysis['summary']['long_summary'], 900);
        $analysis['candidate']['email'] = sanitize_email((string) $analysis['candidate']['email']);
        $analysis['candidate']['full_name'] = $this->text($analysis['candidate']['full_name'], 190);
        $analysis['candidate']['phone'] = $this->text($analysis['candidate']['phone'], 80);
        $analysis['candidate']['location'] = $this->text($analysis['candidate']['location'], 190);

        return $analysis;
    }

    /** @param array<string, mixed> $analysis @return array<string, mixed> */
    public function indexedFields(array $analysis): array
    {
        $primaryJob = (array) ((array) $analysis['career_targeting'])['primary_job'];
        $seniority = (array) $analysis['seniority'];
        $candidate = (array) $analysis['candidate'];
        $summary = (array) $analysis['summary'];

        return [
            'candidate_email' => sanitize_email((string) ($candidate['email'] ?? '')),
            'candidate_phone' => $this->text($candidate['phone'] ?? '', 80),
            'candidate_location' => $this->text($candidate['location'] ?? '', 190),
            'candidate_linkedin_url' => esc_url_raw((string) ($candidate['linkedin_url'] ?? '')),
            'candidate_portfolio_url' => esc_url_raw((string) ($candidate['portfolio_url'] ?? '')),
            'primary_job_label' => $this->text($primaryJob['label'] ?? '', 190),
            'primary_job_normalized' => sanitize_title((string) ($primaryJob['normalized_label'] ?? $primaryJob['label'] ?? '')),
            'primary_job_confidence' => sanitize_key((string) ($primaryJob['confidence'] ?? 'low')),
            'seniority_level' => sanitize_key((string) ($seniority['level'] ?? 'unknown')),
            'seniority_years_estimate' => isset($seniority['years_experience_estimate']) ? absint($seniority['years_experience_estimate']) : null,
            'seniority_confidence' => sanitize_key((string) ($seniority['confidence'] ?? 'low')),
            'summary_short' => $this->text($summary['short_summary'] ?? '', 350),
            'warnings_count' => is_array($analysis['warnings'] ?? null) ? count($analysis['warnings']) : 0,
        ];
    }

    /** @return array<string, mixed> */
    public function emptyAnalysis(): array
    {
        return [
            'candidate' => ['full_name' => '', 'email' => '', 'phone' => '', 'location' => '', 'linkedin_url' => '', 'portfolio_url' => ''],
            'summary' => ['profile_title' => '', 'short_summary' => '', 'long_summary' => ''],
            'career_targeting' => [
                'primary_job' => ['label' => '', 'normalized_label' => '', 'confidence' => 'low', 'evidence' => ''],
                'associated_jobs' => [],
            ],
            'seniority' => ['level' => 'unknown', 'years_experience_estimate' => null, 'confidence' => 'low', 'evidence' => ''],
            'experiences' => [],
            'education' => [],
            'skills' => [],
            'tools' => [],
            'know_how' => [],
            'soft_skills' => [],
            'languages' => [],
            'interests' => [],
            'warnings' => [],
        ];
    }

    private function text(mixed $value, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', sanitize_text_field((string) $value)) ?: '');

        return mb_substr($text, 0, $max);
    }
}
