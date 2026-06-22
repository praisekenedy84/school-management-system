<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Downloadable "fill this in" template for a bulk-import module — a header
 * row plus one illustrative example row, matching exactly the columns the
 * corresponding Import class expects (see App\Imports). Used by
 * App\Services\Reporting\ImportService::template().
 */
class TemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param  list<string>  $headings
     * @param  list<string>  $exampleRow
     */
    public function __construct(
        private readonly array $headings,
        private readonly array $exampleRow = [],
    ) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->exampleRow === [] ? [] : [$this->exampleRow];
    }
}
