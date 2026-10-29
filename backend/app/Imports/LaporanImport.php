<?php

namespace App\Imports;

use App\Imports\Sheets\DataTransaksiSheetImport;
use App\Imports\Sheets\InfoSheetImport;
use App\Imports\Sheets\PenjualanHarianSheetImport;
use App\Imports\Sheets\ProdukPenjualanSheetImport;
use App\Imports\Sheets\SupplierPenjualanSheetImport;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LaporanImport implements Import, WithMultipleSheets, SkipsUnknownSheets
{
    protected InfoSheetImport $infoSheet;

    protected PenjualanHarianSheetImport $salesSheet;

    protected DataTransaksiSheetImport $transactionSheet;

    protected ProdukPenjualanSheetImport $topHighestSheet;

    protected ProdukPenjualanSheetImport $topLowestSheet;

    protected SupplierPenjualanSheetImport $supplierSheet;

    protected int $dayLength;

    protected array $errors = [];

    public function __construct(bool $isGabungan, ?int $dayLength = null)
    {
        $this->infoSheet = new InfoSheetImport;
        $this->salesSheet = new PenjualanHarianSheetImport($isGabungan, $dayLength);
        $this->transactionSheet = new DataTransaksiSheetImport;
        $this->topHighestSheet = new ProdukPenjualanSheetImport('tertinggi');
        $this->topLowestSheet = new ProdukPenjualanSheetImport('terendah');
        $this->supplierSheet = new SupplierPenjualanSheetImport;
    }

    public function sheets(): array
    {
        return [
            'INFO' => $this->infoSheet,
            'PENJUALAN_HARIAN' => $this->salesSheet,
            'TRANSAKSI' => $this->transactionSheet,
            'PRODUK_TERTINGGI' => $this->topHighestSheet,
            'PRODUK_TERENDAH' => $this->topLowestSheet,
            'SUPPLIER' => $this->supplierSheet,
        ];
    }

    public function getInfoData(): array
    {
        return $this->infoSheet->getData();
    }

    public function getSalesHarianData(): array
    {
        return $this->salesSheet->getData();
    }

    public function getTransactionData(): array
    {
        return $this->transactionSheet->getData();
    }

    public function getTopHighestData(): array
    {
        return $this->topHighestSheet->getData();
    }

    public function getTopLowestData(): array
    {
        return $this->topLowestSheet->getData();
    }

    public function getSupplierData(): array
    {
        return $this->supplierSheet->getData();
    }

    public function onUnknownSheet(string|int $sheetName): void
    {
        $this->errors[] = [
            'location' => null,
            'message' => 'Sheet '.($sheetName).' tidak ditemukan!',
        ];
    }

    public function getImportErrors(): array
    {
        $data = [];

        if (! empty($this->errors)) {
            $data['SHEETS'] = $this->errors;
        }

        if (! empty($this->infoSheet->getErrors())) {
            $data['INFO'] = $this->infoSheet->getErrors();
        }

        if (! empty($this->salesSheet->getErrors())) {
            $data['PENJUALAN_HARIAN'] = $this->salesSheet->getErrors();
        }

        if (! empty($this->topHighestSheet->getErrors())) {
            $data['PRODUK_TERINGGI'] = $this->topHighestSheet->getErrors();
        }

        if (! empty($this->topLowestSheet->getErrors())) {
            $data['PRODUK_TERENDAH'] = $this->topLowestSheet->getErrors();
        }

        if (! empty($this->supplierSheet->getErrors())) {
            $data['SUPPLIER'] = $this->supplierSheet->getErrors();
        }

        return $data;
    }
}
