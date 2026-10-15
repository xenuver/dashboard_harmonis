<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class LaporanExcelValidator
{
    protected UploadedFile $file;
    protected string $jenisLaporan;
    protected int $periodeBulan;
    protected int $periodeTahun;

    public function __construct(UploadedFile $file, string $jenisLaporan, int $periodeBulan, int $periodeTahun)
    {
        $this->file         = $file;
        $this->jenisLaporan = $jenisLaporan;
        $this->periodeBulan = $periodeBulan;
        $this->periodeTahun = $periodeTahun;
    }

    public function validate(): array
    {
        $groupedErrors = [];

        try {
            $spreadsheet = IOFactory::load($this->file->getRealPath());
        } catch (\Throwable $e) {
            return [
                'FILE' => [
                    ['location' => null, 'message' => 'File Excel tidak dapat dibaca atau rusak.']
                ]
            ];
        }

        $requiredSheets = [
            'VIEW',
            'DATA',
            'TOP 300 HIGHEST',
            'TOP 300 LOWEST',
            'TOP SUPPLIER',
        ];

        foreach ($requiredSheets as $sheetName) {
            if (!$spreadsheet->sheetNameExists($sheetName)) {
                $groupedErrors[$sheetName][] = [
                    'location' => null,
                    'message'  => 'Sheet tidak ditemukan.',
                ];
            }
        }

        if ($spreadsheet->sheetNameExists('VIEW')) {
            $viewSheet = $spreadsheet->getSheetByName('VIEW');
            $errors = $this->validateViewSheet($viewSheet->toArray(null, true, true, false));
            if (!empty($errors)) {
                $groupedErrors['VIEW'] = array_merge($groupedErrors['VIEW'] ?? [], $errors);
            }
        }

        if ($spreadsheet->sheetNameExists('DATA')) {
            $dataSheet = $spreadsheet->getSheetByName('DATA');
            $errors = $this->validateDataSheet($dataSheet->toArray(null, true, true, false));
            if (!empty($errors)) {
                $groupedErrors['DATA'] = array_merge($groupedErrors['DATA'] ?? [], $errors);
            }
        }

        if ($spreadsheet->sheetNameExists('TOP 300 HIGHEST')) {
            $topHighestSheet = $spreadsheet->getSheetByName('TOP 300 HIGHEST');
            $errors = $this->validateProdukSheet($topHighestSheet->toArray(null, true, true, false), 'TOP 300 HIGHEST');
            if (!empty($errors)) {
                $groupedErrors['TOP 300 HIGHEST'] = array_merge($groupedErrors['TOP 300 HIGHEST'] ?? [], $errors);
            }
        }

        if ($spreadsheet->sheetNameExists('TOP 300 LOWEST')) {
            $topLowestSheet = $spreadsheet->getSheetByName('TOP 300 LOWEST');
            $errors = $this->validateProdukSheet($topLowestSheet->toArray(null, true, true, false), 'TOP 300 LOWEST');
            if (!empty($errors)) {
                $groupedErrors['TOP 300 LOWEST'] = array_merge($groupedErrors['TOP 300 LOWEST'] ?? [], $errors);
            }
        }

        if ($spreadsheet->sheetNameExists('TOP SUPPLIER')) {
            $supplierSheet = $spreadsheet->getSheetByName('TOP SUPPLIER');
            $errors = $this->validateSupplierSheet($supplierSheet->toArray(null, true, true, false));
            if (!empty($errors)) {
                $groupedErrors['TOP SUPPLIER'] = array_merge($groupedErrors['TOP SUPPLIER'] ?? [], $errors);
            }
        }

        return $groupedErrors;
    }

    protected function validateViewSheet(array $rows): array
    {
        $errors = [];
        $row = $rows[4] ?? null;

        if (!$row) {
            $errors[] = ['location' => '5', 'message' => 'Data ringkasan KPI tidak ditemukan.'];
            return $errors;
        }

        // Cell 5G (Index 6) - Total Sales
        $totalSales = $row[6] ?? null;
        if ($totalSales === null || $totalSales === '') {
            $errors[] = ['location' => 'G5', 'message' => 'Total sales tidak boleh kosong.'];
        } elseif (!$this->isNumericValue($totalSales)) {
            $errors[] = ['location' => 'G5', 'message' => 'Total sales harus berupa angka.'];
        }

        // Cell 5L (Index 11) - Total Growth
        $totalGrowth = $row[11] ?? null;
        if ($totalGrowth === null || $totalGrowth === '') {
            $errors[] = ['location' => 'L5', 'message' => 'Total growth tidak boleh kosong.'];
        } elseif (!$this->isNumericValue($totalGrowth)) {
            $errors[] = ['location' => 'L5', 'message' => 'Total growth harus berupa angka.'];
        }

        // Cell 5Q (Index 16) - Jumlah Transaksi
        $jumlahTransaksi = $row[16] ?? null;
        if ($jumlahTransaksi === null || $jumlahTransaksi === '') {
            $errors[] = ['location' => 'Q5', 'message' => 'Jumlah transaksi tidak boleh kosong.'];
        } elseif (!$this->isNumericValue($jumlahTransaksi)) {
            $errors[] = ['location' => 'Q5', 'message' => 'Jumlah transaksi harus berupa angka.'];
        }

        // Cell 5V (Index 21) - Growth Member
        $growthMember = $row[21] ?? null;
        if ($growthMember === null || $growthMember === '') {
            $errors[] = ['location' => 'V5', 'message' => 'Growth member tidak boleh kosong.'];
        } elseif (!preg_match('/^[+-]?[0-9]+$/', trim((string) $growthMember))) {
            $errors[] = ['location' => 'V5', 'message' => 'Growth member harus berupa angka atau format (+NUMBER / -NUMBER).'];
        }

        return $errors;
    }

    protected function validateDataSheet(array $rows): array
    {
        $errors = [];
        $expectedDays = Carbon::createFromDate($this->periodeTahun, $this->periodeBulan, 1)->daysInMonth;

        $dataRows = array_slice($rows, 2, null, true);

        // Find last non-empty row index
        $lastRowIndex = -1;
        foreach ($dataRows as $rowIndex => $row) {
            if (!$this->isRowEmpty($row)) {
                $lastRowIndex = $rowIndex;
            }
        }

        if ($lastRowIndex === -1) {
            $errors[] = [
                'location' => null,
                'message'  => "Jumlah baris data harian (0) tidak sesuai dengan periode {$this->periodeBulan}/{$this->periodeTahun} ({$expectedDays} hari).",
            ];
            return $errors;
        }

        $totalDataRows = $lastRowIndex - 1;
        if ($totalDataRows !== $expectedDays) {
            $errors[] = [
                'location' => null,
                'message'  => "Jumlah baris data harian ({$totalDataRows}) tidak sesuai dengan periode {$this->periodeBulan}/{$this->periodeTahun} ({$expectedDays} hari).",
            ];
        }

        foreach ($dataRows as $rowIndex => $row) {
            if ($rowIndex > $lastRowIndex) {
                break; // Skip trailing empty rows
            }

            $excelRow = $rowIndex + 1;

            if ($this->isRowEmpty($row)) {
                $errors[] = ['location' => (string) $excelRow, 'message' => 'Baris data tidak boleh kosong.'];
                continue;
            }

            $dayVal = $row[3] ?? null;

            // Cell D - Tanggal
            if (!$this->isNumericValue($dayVal) || (int) $dayVal < 1 || (int) $dayVal > $expectedDays) {
                $errors[] = ['location' => "D{$excelRow}", 'message' => "Tanggal harus berupa angka antara 1 dan {$expectedDays}."];
            }

            // Cell E - Total
            $totalVal = $row[4] ?? null;
            if (!$this->isNumericValue($totalVal)) {
                $errors[] = ['location' => "E{$excelRow}", 'message' => 'Total sales harus berupa angka.'];
            }

            $amperaVal = $row[10] ?? null;
            $palVal    = $row[11] ?? null;

            if ($this->jenisLaporan === 'gabungan') {
                if (!$this->isNumericValue($amperaVal)) {
                    $errors[] = ['location' => "K{$excelRow}", 'message' => 'Kolom Ampera wajib diisi untuk laporan gabungan.'];
                }
                if (!$this->isNumericValue($palVal)) {
                    $errors[] = ['location' => "K{$excelRow}", 'message' => 'Kolom Pal wajib diisi untuk laporan gabungan.'];
                }
            } else {
                if ($amperaVal !== null && $amperaVal !== '') {
                    $errors[] = ['location' => "K{$excelRow}", 'message' => 'Kolom Ampera harus kosong untuk laporan non-gabungan.'];
                }
                if ($palVal !== null && $palVal !== '') {
                    $errors[] = ['location' => "K{$excelRow}", 'message' => 'Kolom Pal harus kosong untuk laporan non-gabungan.'];
                }
            }
        }

        return $errors;
    }

    protected function validateProdukSheet(array $rows, string $sheetName): array
    {
        $errors = [];
        $dataRows = array_slice($rows, 2, null, true);

        // Find last non-empty row index
        $lastRowIndex = -1;
        foreach ($dataRows as $rowIndex => $row) {
            if (!$this->isRowEmpty($row)) {
                $lastRowIndex = $rowIndex;
            }
        }

        if ($lastRowIndex === -1) {
            return $errors;
        }

        foreach ($dataRows as $rowIndex => $row) {
            if ($rowIndex > $lastRowIndex) {
                break; // Skip trailing empty rows
            }

            $excelRow = $rowIndex + 1;

            if ($this->isRowEmpty($row)) {
                $errors[] = ['location' => (string) $excelRow, 'message' => 'Baris data tidak boleh kosong.'];
                continue;
            }

            // B: Kode Barang
            if (empty($row[1])) {
                $errors[] = ['location' => "B{$excelRow}", 'message' => 'Kode barang tidak boleh kosong.'];
            }

            // C: Nama Barang
            if (empty($row[2])) {
                $errors[] = ['location' => "C{$excelRow}", 'message' => 'Nama barang tidak boleh kosong.'];
            }

            // E: Harga Jual
            if (!$this->isNumericValue($row[4] ?? null)) {
                $errors[] = ['location' => "E{$excelRow}", 'message' => 'Harga jual harus berupa angka.'];
            }

            // F: Jumlah
            if (!$this->isNumericValue($row[5] ?? null)) {
                $errors[] = ['location' => "F{$excelRow}", 'message' => 'Jumlah penjualan harus berupa angka.'];
            }

            // G: Qty
            if (!$this->isNumericValue($row[6] ?? null)) {
                $errors[] = ['location' => "G{$excelRow}", 'message' => 'Qty penjualan harus berupa angka.'];
            }

            // H: Stok (optional, but if present must be numeric)
            $stokVal = $row[7] ?? null;
            if ($stokVal !== null && $stokVal !== '' && !$this->isNumericValue($stokVal)) {
                $errors[] = ['location' => "H{$excelRow}", 'message' => 'Stok harus berupa angka.'];
            }
        }

        return $errors;
    }

    protected function validateSupplierSheet(array $rows): array
    {
        $errors = [];
        $dataRows = array_slice($rows, 2, null, true);

        // Find last non-empty row index
        $lastRowIndex = -1;
        foreach ($dataRows as $rowIndex => $row) {
            if (!$this->isRowEmpty($row)) {
                $lastRowIndex = $rowIndex;
            }
        }

        if ($lastRowIndex === -1) {
            return $errors;
        }

        foreach ($dataRows as $rowIndex => $row) {
            if ($rowIndex > $lastRowIndex) {
                break; // Skip trailing empty rows
            }

            $excelRow = $rowIndex + 1;

            if ($this->isRowEmpty($row)) {
                $errors[] = ['location' => (string) $excelRow, 'message' => 'Baris data tidak boleh kosong.'];
                continue;
            }

            // A: Kode Supplier
            if (empty($row[0])) {
                $errors[] = ['location' => "A{$excelRow}", 'message' => 'Kode supplier tidak boleh kosong.'];
            }

            // B: Nama Supplier
            if (empty($row[1])) {
                $errors[] = ['location' => "B{$excelRow}", 'message' => 'Nama supplier tidak boleh kosong.'];
            }

            // C: Gross Total
            if (!$this->isNumericValue($row[2] ?? null)) {
                $errors[] = ['location' => "C{$excelRow}", 'message' => 'Gross total harus berupa angka.'];
            }

            // D: Net Sales Total
            if (!$this->isNumericValue($row[3] ?? null)) {
                $errors[] = ['location' => "D{$excelRow}", 'message' => 'Net sales total harus berupa angka.'];
            }
        }

        return $errors;
    }

    protected function isNumericValue(mixed $val, bool $allowPlusSign = false): bool
    {
        if ($val === null || $val === '') {
            return false;
        }

        $str = (string) $val;
        if ($allowPlusSign) {
            $str = str_replace('+', '', $str);
        }

        $str = str_replace(',', '', $str);

        return is_numeric($str);
    }

    protected function isRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }
}