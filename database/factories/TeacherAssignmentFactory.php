<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\ClassRoom;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAssignment>
 */
class TeacherAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_id' => User::factory(),
            'class_id' => ClassRoom::factory(),
            'subject_id' => Subject::factory(),
            'academic_session_id' => AcademicSession::factory(),
        ];
    }

    /**
     * Configure the model factory to derive `school_id` from the parent
     * ClassRoom (falling back to the Subject) rather than creating an
     * unrelated School, so the row stays internally consistent.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (TeacherAssignment $teacherAssignment) {
            if ($teacherAssignment->school_id !== null) {
                return;
            }

            if ($teacherAssignment->class_id !== null) {
                $teacherAssignment->school_id = ClassRoom::find($teacherAssignment->class_id)?->school_id;
            }

            if ($teacherAssignment->school_id === null && $teacherAssignment->subject_id !== null) {
                $teacherAssignment->school_id = Subject::find($teacherAssignment->subject_id)?->school_id;
            }
        });
    }
}
