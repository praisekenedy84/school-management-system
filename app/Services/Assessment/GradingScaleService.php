<?php

declare(strict_types=1);

namespace App\Services\Assessment;

use App\Models\School;

class GradingScaleService
{
    /**
     * @return list<array{min_percent: float|int, grade: string, label: string}>
     */
    public function scaleForSchool(School $school): array
    {
        $scale = $school->grading_scale;

        if (! is_array($scale) || $scale === []) {
            return config('grading-scale-defaults', []);
        }

        return $scale;
    }

    /**
     * Resolve a letter grade from a raw score and assessment max score.
     */
    public function gradeForScore(?float $score, float $maxScore, School $school): ?string
    {
        if ($score === null || $maxScore <= 0) {
            return null;
        }

        $percent = ($score / $maxScore) * 100;
        $scale = $this->scaleForSchool($school);

        usort($scale, fn (array $a, array $b) => ($b['min_percent'] ?? 0) <=> ($a['min_percent'] ?? 0));

        foreach ($scale as $band) {
            if ($percent >= (float) ($band['min_percent'] ?? 0)) {
                return (string) ($band['grade'] ?? null);
            }
        }

        return null;
    }
}
