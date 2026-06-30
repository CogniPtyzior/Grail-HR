<?php
/**
 * Tests strict validation boundaries for analysis JSON.
 */

declare(strict_types=1);

namespace GrailHr\Tests\Unit;

use GrailHr\ProfileAnalysis\Application\AnalysisNormalizer;
use GrailHr\ProfileAnalysis\Application\AnalysisValidator;
use GrailHr\Shared\Domain\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class AnalysisValidatorTest extends TestCase
{
    public function testItAcceptsEmptyNormalizedAnalysis(): void
    {
        $analysis = (new AnalysisNormalizer())->emptyAnalysis();
        (new AnalysisValidator())->validate($analysis);
        self::assertTrue(true);
    }

    public function testItRejectsInvalidSeniority(): void
    {
        $this->expectException(ValidationException::class);
        $analysis = (new AnalysisNormalizer())->emptyAnalysis();
        $analysis['seniority']['level'] = 'invalid';
        (new AnalysisValidator())->validate($analysis);
    }
}
