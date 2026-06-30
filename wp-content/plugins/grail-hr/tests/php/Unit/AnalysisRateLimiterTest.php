<?php
/**
 * Tests rate limiting around paid AI analysis usage.
 */

declare(strict_types=1);

namespace GrailHr\Tests\Unit;

use GrailHr\CvIntake\Application\AnalysisRateLimiter;
use GrailHr\Settings\Infrastructure\SettingsRepository;
use GrailHr\Shared\Domain\Exception\GrailHrException;
use PHPUnit\Framework\TestCase;

final class AnalysisRateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        global $grailHrTestOptions, $grailHrTestTransients;

        $grailHrTestOptions = [
            'grail_hr_settings' => [
                'analyses_per_hour' => 2,
            ],
        ];
        $grailHrTestTransients = [];
    }

    public function testItTracksRemainingAnalysesAfterRecordingUsage(): void
    {
        $limiter = new AnalysisRateLimiter(new SettingsRepository());

        self::assertSame(2, $limiter->remaining(123));

        $limiter->record(123);

        self::assertSame(1, $limiter->remaining(123));
    }

    public function testItRejectsWhenHourlyLimitIsReached(): void
    {
        $limiter = new AnalysisRateLimiter(new SettingsRepository());

        $limiter->record(123);
        $limiter->record(123);

        $this->expectException(GrailHrException::class);
        $limiter->assertAllowed(123);
    }
}
