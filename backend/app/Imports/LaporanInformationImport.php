<?php

namespace App\Imports;

use App\Imports\Sheets\InfoSheetImport;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LaporanInformationImport implements Import, WithMultipleSheets
{
    protected InfoSheetImport $infoSheet;

    public function __construct()
    {
        $this->infoSheet = new InfoSheetImport;
    }

    public function sheets(): array
    {
        return [
            'INFO' => $this->infoSheet,
        ];
    }

    public function getInfoData(): array
    {
        return $this->infoSheet->getData();
    }

    public function getIsGabungan(): bool
    {
        return $this->infoSheet->getData()['jenis_laporan'] === "GABUNGAN";
    }

    public function getImportErrors(): array
    {
        $data = [];

        if(!empty($this->infoSheet->getErrors())) {
            $data['INFO'] = $this->infoSheet->getErrors();
        }

        return $data;
    }
}
