<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\ClassRoom;
use App\Support\Import\ImportResult;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

/**
 * Bulk class import — columns: name, level. school_id is fixed for the
 * whole batch, mirroring SubjectsImport.
 */
class ClassesImport implements ToCollection, WithHeadingRow
{
    private readonly ImportResult $result;

    public function __construct(private readonly string $schoolId)
    {
        $this->result = new ImportResult;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $name = trim((string) ($row['name'] ?? ''));
            $level = $row['level'] ?? null;

            if ($name === '') {
                $this->result->recordError($rowNumber, 'Name is required.');

                continue;
            }

            if (ClassRoom::where('school_id', $this->schoolId)->where('name', $name)->exists()) {
                $this->result->recordError($rowNumber, "A class named \"{$name}\" already exists.");

                continue;
            }

            try {
                ClassRoom::create([
                    'school_id' => $this->schoolId,
                    'name' => $name,
                    'level' => $level !== null && $level !== '' ? (int) $level : null,
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
