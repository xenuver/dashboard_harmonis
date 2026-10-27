<?php

namespace App\Services;

use App\Imports\LaporanInformationImport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;

class InfoLaporanExcelParser
{
    protected LaporanInformationImport $importer;

    public function __construct(UploadedFile $file)
    {
        $this->importer = new LaporanInformationImport;
        Excel::import($this->importer, $file, null, ExcelType::XLSX);
    }

    public function parseInfo(): array
    {
        return $this->importer->getInfoData();
    }

    public function getImportErrors(): array
    {
        return $this->importer->getImportErrors();
    }

    public function getIsGabungan(): bool
    {
        return $this->importer->getIsGabungan();
    }
}
