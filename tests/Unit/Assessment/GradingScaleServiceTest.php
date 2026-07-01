<?php

declare(strict_types=1);

namespace Tests\Feature\Assessment;

use App\Models\School;
use App\Services\Assessment\GradingScaleService;
use Tests\TestCase;

class GradingScaleServiceTest extends TestCase
{
    public function test_grade_for_score_uses_school_bands(): void
    {
        $school = School::factory()->make([
            'grading_scale' => [
                ['min_percent' => 75, 'grade' => 'A'],
                ['min_percent' => 50, 'grade' => 'C'],
                ['min_percent' => 0, 'grade' => 'F'],
            ],
        ]);

        $service = new GradingScaleService;

        $this->assertSame('A', $service->gradeForScore(80.0, 100.0, $school));
        $this->assertSame('C', $service->gradeForScore(55.0, 100.0, $school));
        $this->assertSame('F', $service->gradeForScore(30.0, 100.0, $school));
    }
}
