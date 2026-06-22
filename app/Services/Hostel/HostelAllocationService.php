<?php

declare(strict_types=1);

namespace App\Services\Hostel;

use App\Events\Hostel\HostelAllocationChanged;
use App\Models\HostelAllocation;
use App\Models\HostelRoom;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Allocates a boarding student to a room. Recipe-style: validate
 * capacity + gender match, then insert inside a transaction with the room
 * row locked, so two concurrent allocations can't both squeeze into the
 * last bed.
 */
class HostelAllocationService
{
    public function allocate(array $data, ?string $allocatedBy): HostelAllocation
    {
        return DB::transaction(function () use ($data, $allocatedBy) {
            /** @var HostelRoom $room */
            $room = HostelRoom::with('hostel.school')->lockForUpdate()->findOrFail($data['hostel_room_id']);
            $student = Student::findOrFail($data['student_id']);

            if ($room->activeOccupantCount() >= $room->capacity) {
                throw ValidationException::withMessages([
                    'hostel_room_id' => 'This room is already at full capacity.',
                ]);
            }

            $hostelGender = $room->hostel->gender;
            if ($hostelGender !== 'mixed' && $student->gender !== null && $student->gender !== $hostelGender) {
                throw ValidationException::withMessages([
                    'hostel_room_id' => 'This room\'s hostel does not match the student\'s gender.',
                ]);
            }

            // DB has a partial unique index (one active allocation per
            // student per session) — check it explicitly so a re-allocate
            // attempt surfaces as a clean 422, not an uncaught 500.
            $alreadyAllocated = HostelAllocation::where('student_id', $student->id)
                ->where('academic_session_id', $data['academic_session_id'])
                ->where('status', 'active')
                ->exists();

            if ($alreadyAllocated) {
                throw ValidationException::withMessages([
                    'student_id' => 'This student already has an active hostel allocation for this session.',
                ]);
            }

            if ($this->hostelGateEnabled($room) && $this->hasOutstandingBalance($student->id, $data['academic_session_id'])) {
                throw ValidationException::withMessages([
                    'student_id' => 'This student has an outstanding fee balance for this session and cannot be allocated a room.',
                ]);
            }

            $allocation = HostelAllocation::create([
                'school_id' => $room->school_id,
                'student_id' => $student->id,
                'hostel_room_id' => $room->id,
                'academic_session_id' => $data['academic_session_id'],
                'status' => 'active',
                'allocated_at' => now()->toDateString(),
                'allocated_by' => $allocatedBy,
            ]);

            HostelAllocationChanged::dispatch($allocation, 'allocated', Auth::user());

            return $allocation;
        });
    }

    public function end(HostelAllocation $allocation): HostelAllocation
    {
        $allocation->update([
            'status' => 'ended',
            'ended_at' => now()->toDateString(),
        ]);

        HostelAllocationChanged::dispatch($allocation, 'ended', Auth::user());

        return $allocation;
    }

    /**
     * Optional fee-status gate (PRD §5.7), same opt-in pattern as the
     * report-card gate (GenerateReportCardPdf::resultsGateEnabled) — a
     * separate fee_terms key so a school can enable one without the other.
     * Defaults OFF, so existing allocate tests are unaffected.
     */
    private function hostelGateEnabled(HostelRoom $room): bool
    {
        return (bool) ($room->hostel->school?->fee_terms['hostel_gate_enabled'] ?? false);
    }

    private function hasOutstandingBalance(string $studentId, string $academicSessionId): bool
    {
        $ledger = StudentFeeLedger::where('student_id', $studentId)
            ->where('academic_session_id', $academicSessionId)
            ->first();

        // No ledger yet → nothing recorded as assessed/paid → don't block
        // allocation on the absence of data.
        if ($ledger === null) {
            return false;
        }

        $ledger->refresh();

        return (float) $ledger->balance > 0;
    }
}
