<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\Subject;
use App\Support\Import\ImportResult;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

/**
 * Bulk subject import — columns: name, code. school_id is fixed for the
 * whole batch (resolved by the controller, same "school_admin's own /
 * tenant_admin must choose one" rule as SubjectRequest::prepareForValidation),
 * never read from the sheet itself.
 */
class SubjectsImport implements ToCollection, WithHeadingRow
{
    private readonly ImportResult $result;

    public function __construct(private readonly string $schoolId)
    {
        $this->result = new ImportResult;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 for zero-index, +1 for the header row

            $name = trim((string) ($row['name'] ?? ''));
            $code = trim((string) ($row['code'] ?? ''));

            if ($name === '') {
                $this->result->recordError($rowNumber, 'Name is required.');

                continue;
            }

            if (Subject::where('school_id', $this->schoolId)->where('name', $name)->exists()) {
                $this->result->recordError($rowNumber, "A subject named \"{$name}\" already exists.");

                continue;
            }

            try {
                Subject::create([
                    'school_id' => $this->schoolId,
                    'name' => $name,
                    'code' => $code !== '' ? $code : null,
                ]);
                $this->result->recordCreated();
            } catch (Throwable) {
                $this->result->recordError($rowNumber, 'Could not save this row.');
            }
        }
    }

    public function result(): ImportResult
    {
        return $this->result;
    }
}
