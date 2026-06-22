<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Support\Import\ImportResult;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Runs any App\Imports\* class (each implements ToCollection +
 * WithHeadingRow and exposes result(): ImportResult) against an uploaded
 * file and hands back the row-by-row outcome. Template downloads reuse
 * ExportService::template() instead — this is read-the-file-in, not
 * generate-a-file-out.
 */
class ImportService
{
    public function run(object $importer, UploadedFile $file): ImportResult
    {
        Excel::import($importer, $file);

        return $importer->result();
    }
}
