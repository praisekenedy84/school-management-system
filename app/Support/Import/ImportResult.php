<?php

declare(strict_types=1);

namespace App\Support\Import;

/**
 * Outcome of a bulk-import run — every App\Imports\* class accumulates one
 * of these instead of aborting on the first bad row, so a 200-row sheet
 * with 3 typos still imports the other 197 and reports exactly which rows
 * failed and why.
 */
class ImportResult
{
    /** @var list<array{row: int, message: string}> */
    private array $errors = [];

    private int $created = 0;

    public function recordCreated(): void
    {
        $this->created++;
    }

    public function recordError(int $row, string $message): void
    {
        $this->errors[] = ['row' => $row, 'message' => $message];
    }

    public function createdCount(): int
    {
        return $this->created;
    }

    /** @return array{created: int, failed: int, errors: list<array{row: int, message: string}>} */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'failed' => count($this->errors),
            'errors' => $this->errors,
        ];
    }
}
