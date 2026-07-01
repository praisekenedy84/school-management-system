<?php

declare(strict_types=1);

/**
 * Default letter-grade bands (% of max score) for East African schools.
 * Stored on `schools.grading_scale` JSONB; editable per school.
 */
return [
    ['min_percent' => 75, 'grade' => 'A', 'label' => 'Excellent'],
    ['min_percent' => 65, 'grade' => 'B', 'label' => 'Good'],
    ['min_percent' => 50, 'grade' => 'C', 'label' => 'Average'],
    ['min_percent' => 40, 'grade' => 'D', 'label' => 'Below Average'],
    ['min_percent' => 0, 'grade' => 'F', 'label' => 'Fail'],
];
