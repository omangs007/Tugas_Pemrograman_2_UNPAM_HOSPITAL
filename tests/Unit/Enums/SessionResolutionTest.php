<?php

namespace Tests\Unit\Enums;

use App\Enums\SessionResolution;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SessionResolutionTest extends TestCase
{
    /** @return array<string, array{0: string, 1: list<SessionResolution>}> */
    public static function allowedBySource(): array
    {
        // Peta statis 00 F.4 / 10 §4.2
        return [
            'exception' => ['exception', [SessionResolution::Keep, SessionResolution::Cancel]],
            'template_time' => ['template_time', [SessionResolution::Keep, SessionResolution::Apply, SessionResolution::Cancel]],
            'template_removed' => ['template_removed', [SessionResolution::Keep, SessionResolution::Cancel]],
            'manual_time' => ['manual_time', [SessionResolution::Keep, SessionResolution::Apply, SessionResolution::Cancel]],
            'manual_cancel' => ['manual_cancel', [SessionResolution::Keep, SessionResolution::Cancel]],
            'doctor_inactive' => ['doctor_inactive', [SessionResolution::Keep, SessionResolution::Cancel]],
        ];
    }

    /** @param  list<SessionResolution>  $expected */
    #[DataProvider('allowedBySource')]
    public function test_allowed_resolutions_per_source(string $source, array $expected): void
    {
        $this->assertSame($expected, SessionResolution::allowedFor($source));
    }

    public function test_unknown_source_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SessionResolution::allowedFor('sumber_ngawur');
    }

    public function test_only_cancel_requires_a_reason(): void
    {
        $this->assertTrue(SessionResolution::Cancel->requiresReason());
        $this->assertFalse(SessionResolution::Keep->requiresReason());
        $this->assertFalse(SessionResolution::Apply->requiresReason());
    }

    public function test_every_resolution_has_a_distinct_indonesian_description(): void
    {
        $descriptions = array_map(
            static fn (SessionResolution $resolution): string => $resolution->description(),
            SessionResolution::cases(),
        );

        foreach ($descriptions as $description) {
            $this->assertNotSame('', trim($description));
        }

        $this->assertCount(count(SessionResolution::cases()), array_unique($descriptions));
    }
}
