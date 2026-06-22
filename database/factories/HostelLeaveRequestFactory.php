<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HostelAllocation;
use App\Models\HostelLeaveRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HostelLeaveRequest>
 */
class HostelLeaveRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hostel_allocation_id' => HostelAllocation::factory(),
            'reason' => fake()->sentence(8),
            'depart_at' => now()->addDays(3)->toDateString(),
            'return_at' => now()->addDays(5)->toDateString(),
            'status' => 'pending',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (HostelLeaveRequest $request) {
            if ($request->hostel_allocation_id === null) {
                return;
            }

            $allocation = HostelAllocation::find($request->hostel_allocation_id);

            if ($allocation === null) {
                return;
            }

            $request->school_id ??= $allocation->school_id;
            $request->student_id ??= $allocation->student_id;
        });
    }
}
