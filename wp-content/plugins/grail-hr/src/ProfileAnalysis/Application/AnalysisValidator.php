<?php
/**
 * Authoritative backend validator for the current analysis JSON.
 *
 * The validator is intentionally handcrafted for V1 so the plugin avoids a heavy JSON Schema runtime dependency while remaining strict.
 */

declare(strict_types=1);

namespace GrailHr\ProfileAnalysis\Application;

use GrailHr\Shared\Domain\Exception\ValidationException;

final class AnalysisValidator
{
    private const CONFIDENCE = ['low', 'medium', 'high'];
    private const SENIORITY = ['junior', 'confirmed', 'senior', 'lead', 'manager', 'expert', 'unknown'];

    /** @param array<string, mixed> $analysis */
    public function validate(array $analysis): void
    {
        $errors = [];

        foreach (['candidate', 'summary', 'career_targeting', 'seniority'] as $key) {
            if (!isset($analysis[$key]) || !is_array($analysis[$key])) {
                $errors[$key] = 'Section obligatoire manquante.';
            }
        }

        foreach (['experiences', 'education', 'skills', 'tools', 'know_how', 'soft_skills', 'languages', 'interests', 'warnings'] as $key) {
            if (!isset($analysis[$key]) || !is_array($analysis[$key])) {
                $errors[$key] = 'Liste obligatoire manquante.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException('Le profil contient des données invalides.', $errors);
        }

        $this->validateSummary($analysis['summary'], $errors);
        $this->validateSeniority($analysis['seniority'], $errors);

        if ($errors !== []) {
            throw new ValidationException('Le profil contient des données invalides.', $errors);
        }
    }

    /** @param array<string, mixed> $summary @param array<string, string> $errors */
    private function validateSummary(array $summary, array &$errors): void
    {
        if (mb_strlen((string) ($summary['short_summary'] ?? '')) > 350) {
            $errors['summary.short_summary'] = 'Le résumé court est trop long.';
        }

        if (mb_strlen((string) ($summary['long_summary'] ?? '')) > 900) {
            $errors['summary.long_summary'] = 'Le résumé long est trop long.';
        }
    }

    /** @param array<string, mixed> $seniority @param array<string, string> $errors */
    private function validateSeniority(array $seniority, array &$errors): void
    {
        $level = (string) ($seniority['level'] ?? 'unknown');
        $confidence = (string) ($seniority['confidence'] ?? 'low');

        if (!in_array($level, self::SENIORITY, true)) {
            $errors['seniority.level'] = 'Niveau de seniorité invalide.';
        }

        if (!in_array($confidence, self::CONFIDENCE, true)) {
            $errors['seniority.confidence'] = 'Confiance invalide.';
        }
    }
}
