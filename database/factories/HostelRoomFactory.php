<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hostel;
use App\Models\HostelRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HostelRoom>
 */
class HostelRoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hostel_id' => Hostel::factory(),
            'room_number' => fake()->unique()->bothify('R-###'),
            'capacity' => fake()->numberBetween(2, 6),
            'is_active' => true,
        ];
    }

    /**
     * Derive school_id from the parent Hostel so the row stays consistent
     * (mirrors StreamFactory's pattern from Phase 0).
     */
    public function configure(): static
    {
        return $this->afterMaking(function (HostelRoom $room) {
            if ($room->school_id !== null || $room->hostel_id === null) {
                return;
            }

            $room->school_id = Hostel::find($room->hostel_id)?->school_id;
        });
    }
}
