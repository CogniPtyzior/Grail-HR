<?php
/**
 * Tests the analysis normalizer without a WordPress test database.
 */

declare(strict_types=1);

namespace GrailHr\Tests\Unit;

use GrailHr\ProfileAnalysis\Application\AnalysisNormalizer;
use PHPUnit\Framework\TestCase;

final class AnalysisNormalizerTest extends TestCase
{
    public function testItBuildsIndexedFieldsFromAnalysis(): void
    {
        $normalizer = new AnalysisNormalizer();
        $analysis = $normalizer->emptyAnalysis();
        $analysis['candidate']['email'] = 'candidate@example.com';
        $analysis['candidate']['phone'] = '  0600000000 ';
        $analysis['career_targeting']['primary_job']['label'] = 'Développeur WordPress';
        $analysis['career_targeting']['primary_job']['confidence'] = 'high';
        $analysis['seniority']['level'] = 'senior';
        $analysis['warnings'] = [['message' => 'Date manquante']];

        $fields = $normalizer->indexedFields($analysis);

        self::assertSame('candidate@example.com', $fields['candidate_email']);
        self::assertSame('0600000000', $fields['candidate_phone']);
        self::assertSame('Développeur WordPress', $fields['primary_job_label']);
        self::assertSame('senior', $fields['seniority_level']);
        self::assertSame(1, $fields['warnings_count']);
    }
}
