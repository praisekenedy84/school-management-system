<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Exports\GenericListExport;
use App\Exports\TemplateExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * One shared export mechanism for every listing page (PRD §5.9: "PDF/Excel
 * export"). Each controller's export() action supplies its own rows + a
 * data_get() path => heading column map; this never needs a per-module
 * Export class. Read-only — nothing here mutates state, so no service-layer
 * transaction/event is needed (mirrors how plain index() actions work).
 */
class ExportService
{
    /**
     * @param  Collection<int, mixed>  $rows
     * @param  array<string, string>  $columns
     */
    public function excel(Collection $rows, array $columns, string $filename): BinaryFileResponse
    {
        return Excel::download(new GenericListExport($rows, $columns), "{$filename}.xlsx");
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @param  array<string, string>  $columns
     */
    public function pdf(Collection $rows, array $columns, string $filename, string $title): Response
    {
        $pdf = Pdf::loadView('exports.list', [
            'title' => $title,
            'columns' => $columns,
            'rows' => $rows,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("{$filename}.pdf");
    }

    /**
     * @param  list<string>  $headings
     * @param  list<string>  $exampleRow
     */
    public function template(array $headings, array $exampleRow, string $filename): BinaryFileResponse
    {
        return Excel::download(new TemplateExport($headings, $exampleRow), "{$filename}-template.xlsx");
    }
}
