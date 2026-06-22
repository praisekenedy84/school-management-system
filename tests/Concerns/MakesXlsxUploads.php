<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds a real, parseable .xlsx file for import-endpoint tests —
 * `UploadedFile::fake()->create()` only produces a file of junk bytes, not
 * something Maatwebsite\Excel can actually read rows from.
 */
trait MakesXlsxUploads
{
    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     */
    protected function makeXlsxUpload(array $headings, array $rows, string $filename = 'import.xlsx'): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headings, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = sys_get_temp_dir().'/'.Str::random(12).'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
