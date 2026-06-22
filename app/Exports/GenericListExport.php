<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * One reusable Excel export for every listing page — callers supply the
 * rows and a `data_get()` path => column heading map, so no module needs
 * its own Export class. Used by App\Services\Reporting\ExportService.
 */
class GenericListExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, mixed>  $rows
     * @param  array<string, string>  $columns  data_get() path => column heading
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $columns,
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return array_values($this->columns);
    }

    public function map($row): array
    {
        return array_map(
            fn (string $path) => (string) (data_get($row, $path) ?? ''),
            array_keys($this->columns)
        );
    }
}
