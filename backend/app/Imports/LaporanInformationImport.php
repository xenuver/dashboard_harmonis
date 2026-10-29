<?php

namespace App\Imports;

use App\Imports\Sheets\InfoSheetImport;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LaporanInformationImport implements Import, WithMultipleSheets, SkipsUnknownSheets
{
    protected InfoSheetImport $infoSheet;

    protected array $errors = [];

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
        return $this->infoSheet->getData()['jenis_laporan'] === 'GABUNGAN';
    }

    public function onUnknownSheet(string|int $sheetName): void
    {
        $this->errors[] = [
            'location' => null,
            'message' => 'Sheet '.($sheetName).' tidak ditemukan!'
        ];
    }

    public function getImportErrors(): array
    {
        $data = [];

        if(! empty($this->errors)) {
            $data['SHEETS'] = $this->errors;
        }

        if (! empty($this->infoSheet->getErrors())) {
            $data['INFO'] = $this->infoSheet->getErrors();
        }

        return $data;
    }
}
