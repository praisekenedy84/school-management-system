<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HostelAllocation>
 */
class HostelAllocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'hostel_room_id' => HostelRoom::factory(),
            'status' => 'active',
            'allocated_at' => now()->toDateString(),
        ];
    }

    /**
     * Derive school_id from the parent HostelRoom (mirrors EnrolmentFactory's pattern).
     */
    public function configure(): static
    {
        return $this->afterMaking(function (HostelAllocation $allocation) {
            if ($allocation->school_id !== null || $allocation->hostel_room_id === null) {
                return;
            }

            $allocation->school_id = HostelRoom::find($allocation->hostel_room_id)?->school_id;
        });
    }
}
