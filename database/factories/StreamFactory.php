<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassRoom;
use App\Models\Stream;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stream>
 */
class StreamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => ClassRoom::factory(),
            'name' => fake()->randomElement(['Blue', 'Green', 'Red', 'Yellow']),
            'capacity' => fake()->numberBetween(20, 45),
        ];
    }

    /**
     * Configure the model factory to derive `school_id` from the parent
     * ClassRoom rather than creating an unrelated School.
     */
    public function configure(): static
    {
        return $this->afterMaking(function ($stream) {
            if ($stream->school_id === null && $stream->class_id !== null) {
                $stream->school_id = ClassRoom::find($stream->class_id)?->school_id;
            }
        });
    }
}
