<?php

namespace Tests\Helpers;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelReportFactory
{
    public const SHEET_INFO = 'INFO';

    public const SHEET_PENJUALAN_HARIAN = 'PENJUALAN_HARIAN';

    public const SHEET_TRANSAKSI = 'TRANSAKSI';

    public const SHEET_PRODUK_TERTINGGI = 'PRODUK_TERTINGGI';

    public const SHEET_PRODUK_TERENDAH = 'PRODUK_TERENDAH';

    public const SHEET_SUPPLIER = 'SUPPLIER';

    private const DEFAULT_SHEET_ORDER = [
        self::SHEET_INFO,
        self::SHEET_PENJUALAN_HARIAN,
        self::SHEET_TRANSAKSI,
        self::SHEET_PRODUK_TERTINGGI,
        self::SHEET_PRODUK_TERENDAH,
        self::SHEET_SUPPLIER,
    ];

    /** @var list<string> */
    private static array $tempFiles = [];

    private static bool $shutdownRegistered = false;

    private string $jenisLaporan = 'gabungan';

    private int $bulan = 11;

    private int $tahun = 2026;

    private ?int $jumlahHari = null;

    private string $formatData = 'DATA MENTAH v2';

    private ?string $catatan = null;

    private int $topProductCount = 10;

    private int $worstProductCount = 10;

    private int $supplierCount = 10;

    private ?int $salesCount = null;

    private int $salesMin = 100_000_000;

    private int $salesMax = 500_000_000;

    /** @var array<int, array{tanggal: int, ampera?: int, pal?: int, total: int}>|null */
    private ?array $dailySalesOverride = null;

    /** @var array{transaksi_member: int, transaksi_non_member: int, member_baru: int}|null */
    private ?array $transactionOverride = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $topProductsOverride = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $worstProductsOverride = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $suppliersOverride = null;

    /** @var array<string, mixed> */
    private array $infoOverrides = [];

    /** @var array<string, bool> */
    private array $omittedInfoFields = [];

    /** @var array<string, bool> */
    private array $omittedSheets = [];

    /** @var array<string, bool> */
    private array $emptySheets = [];

    /** @var array<string, array<int, array<int, mixed>>> */
    private array $sheetRowsOverrides = [];

    /** @var array<string, array<int, string>> */
    private array $omittedColumns = [];

    /** @var array<string, array<int, array{row: int, column: string|int, value: mixed}>> */
    private array $cellOverrides = [];

    /** @var array<string, mixed> */
    private array $lastBuiltData = [];

    private function __construct()
    {
        if (! self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function([self::class, 'cleanup']);
        }
    }

    public static function new(): static
    {
        return new static;
    }

    public static function cleanup(): void
    {
        foreach (self::$tempFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        self::$tempFiles = [];
    }

    public function jenis_laporan(string $value): static
    {
        $normalized = strtolower(trim($value));

        if (! in_array($normalized, ['ampera', 'pal', 'gabungan'], true)) {
            throw new InvalidArgumentException('Jenis laporan hanya menerima ampera, pal, dan gabungan.');
        }

        $this->jenisLaporan = $normalized;

        return $this;
    }

    public function periode(int $bulan, int $tahun): static
    {
        if ($bulan < 1 || $bulan > 12) {
            throw new InvalidArgumentException('Bulan hanya menerima nilai 1-12.');
        }

        if ($tahun < 0 || $tahun > 65535) {
            throw new InvalidArgumentException('Tahun hanya menerima nilai 0-65535.');
        }

        $this->bulan = $bulan;
        $this->tahun = $tahun;

        return $this;
    }

    public function jumlah_hari(?int $hari): static
    {
        $this->jumlahHari = $hari;

        return $this;
    }

    public function format(string $value): static
    {
        $this->formatData = $value;

        return $this;
    }

    public function catatan(?string $value): static
    {
        $this->catatan = $value;

        return $this;
    }

    public function withTopProductCount(int $count): static
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Jumlah produk tertinggi tidak boleh di bawah 0.');
        }

        $this->topProductCount = $count;

        return $this;
    }

    public function withWorstProductCount(int $count): static
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Jumlah produk terendah tidak boleh di bawah 0.');
        }

        $this->worstProductCount = $count;

        return $this;
    }

    public function withSupplierCount(int $count): static
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Jumlah supplier tidak boleh di bawah 0.');
        }

        $this->supplierCount = $count;

        return $this;
    }

    public function withSalesCount(?int $count): static
    {
        if ($count !== null && $count < 0) {
            throw new InvalidArgumentException('Jumlah baris penjualan harian tidak boleh di bawah 0.');
        }

        $this->salesCount = $count;

        return $this;
    }

    public function withSalesRange(int $min, int $max): static
    {
        if ($min < 0 || $max < $min) {
            throw new InvalidArgumentException('Rentang penjualan harian tidak valid.');
        }

        $this->salesMin = $min;
        $this->salesMax = $max;

        return $this;
    }

    /**
     * @param  array<int, array{tanggal: int, ampera?: int, pal?: int, total: int}>  $rows
     */
    public function withDailySales(array $rows): static
    {
        $this->dailySalesOverride = $rows;

        return $this;
    }

    public function withTransactionData(int $transaksiMember, int $transaksiNonMember, int $memberBaru): static
    {
        $this->transactionOverride = [
            'transaksi_member' => $transaksiMember,
            'transaksi_non_member' => $transaksiNonMember,
            'member_baru' => $memberBaru,
        ];

        return $this;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function withTopProducts(array $rows): static
    {
        $this->topProductsOverride = $rows;

        return $this;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function withWorstProducts(array $rows): static
    {
        $this->worstProductsOverride = $rows;

        return $this;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function withSuppliers(array $rows): static
    {
        $this->suppliersOverride = $rows;

        return $this;
    }

    public function withInfoValue(string $key, mixed $value): static
    {
        $normalizedKey = strtoupper(trim($key));
        unset($this->omittedInfoFields[$normalizedKey]);
        $this->infoOverrides[$normalizedKey] = $value;

        return $this;
    }

    public function withEmptyInfoField(string $key, mixed $emptyValue = null): static
    {
        return $this->withInfoValue($key, $emptyValue);
    }

    public function withoutInfoField(string $key): static
    {
        $normalizedKey = strtoupper(trim($key));
        unset($this->infoOverrides[$normalizedKey]);
        $this->omittedInfoFields[$normalizedKey] = true;

        return $this;
    }

    public function withoutSheet(string $sheetName): static
    {
        $this->omittedSheets[strtoupper(trim($sheetName))] = true;

        return $this;
    }

    public function withEmptySheet(string $sheetName): static
    {
        $this->emptySheets[strtoupper(trim($sheetName))] = true;

        return $this;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function withSheetRows(string $sheetName, array $rows): static
    {
        $this->sheetRowsOverrides[strtoupper(trim($sheetName))] = $rows;

        return $this;
    }

    public function withoutColumn(string $sheetName, string $columnName): static
    {
        $sheetKey = strtoupper(trim($sheetName));
        $this->omittedColumns[$sheetKey][] = strtoupper(trim($columnName));

        return $this;
    }

    public function withCellValue(string $sheetName, int $rowNumber, string|int $column, mixed $value): static
    {
        if ($rowNumber < 1) {
            throw new InvalidArgumentException('Nomor baris Excel minimal 1.');
        }

        $sheetKey = strtoupper(trim($sheetName));
        $this->cellOverrides[$sheetKey][] = [
            'row' => $rowNumber,
            'column' => $column,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastBuiltData(): array
    {
        return $this->lastBuiltData;
    }

    public function toSpreadsheet(): Spreadsheet
    {
        $daysInMonth = Carbon::create($this->tahun, $this->bulan, 1)->daysInMonth;
        $resolvedJumlahHari = $this->jumlahHari ?? $daysInMonth;
        $isGabungan = $this->resolveIsGabungan();

        $infoMap = [
            'JENIS_LAPORAN' => strtoupper($this->jenisLaporan),
            'BULAN' => $this->bulan,
            'TAHUN' => $this->tahun,
            'JUMLAH_HARI' => $resolvedJumlahHari,
            'FORMAT' => $this->formatData,
            'CATATAN' => $this->catatan,
        ];

        foreach ($this->infoOverrides as $key => $value) {
            $infoMap[$key] = $value;
        }

        $dailySales = $this->dailySalesOverride ?? $this->generateDailySales(
            $this->salesCount ?? $daysInMonth,
            $isGabungan
        );

        $transaction = $this->transactionOverride ?? [
            'transaksi_member' => fake()->numberBetween(100, 10_000),
            'transaksi_non_member' => fake()->numberBetween(100, 10_000),
            'member_baru' => fake()->numberBetween(0, 1_000),
        ];

        $topProducts = $this->topProductsOverride ?? $this->generateProducts($this->topProductCount, 'tertinggi');
        $worstProducts = $this->worstProductsOverride ?? $this->generateProducts($this->worstProductCount, 'terendah');
        $suppliers = $this->suppliersOverride ?? $this->generateSuppliers($this->supplierCount);

        $this->lastBuiltData = [
            'info' => $infoMap,
            'sales' => $dailySales,
            'transaksi' => $transaction,
            'top_products' => $topProducts,
            'worst_products' => $worstProducts,
            'suppliers' => $suppliers,
        ];

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach (self::DEFAULT_SHEET_ORDER as $sheetName) {
            if (isset($this->omittedSheets[$sheetName])) {
                continue;
            }

            $worksheet = new Worksheet($spreadsheet, $sheetName);
            $spreadsheet->addSheet($worksheet);

            if (isset($this->emptySheets[$sheetName])) {
                continue;
            }

            $rows = $this->sheetRowsOverrides[$sheetName] ?? match ($sheetName) {
                self::SHEET_INFO => $this->buildInfoRows($infoMap),
                self::SHEET_PENJUALAN_HARIAN => $this->buildDailySalesRows($dailySales, $isGabungan),
                self::SHEET_TRANSAKSI => $this->buildTransactionRows($transaction),
                self::SHEET_PRODUK_TERTINGGI => $this->buildProductRows($topProducts),
                self::SHEET_PRODUK_TERENDAH => $this->buildProductRows($worstProducts),
                self::SHEET_SUPPLIER => $this->buildSupplierRows($suppliers),
            };

            if (! empty($this->omittedColumns[$sheetName]) && ! empty($rows)) {
                $rows = $this->removeColumns($rows, $this->omittedColumns[$sheetName]);
            }

            if (! empty($rows)) {
                $worksheet->fromArray($rows, null, 'A1', true);
            }

            foreach ($this->cellOverrides[$sheetName] ?? [] as $override) {
                $colLetter = $this->resolveColumnLetter($rows[0] ?? [], $override['column']);
                $worksheet->setCellValue($colLetter.$override['row'], $override['value']);
            }
        }

        return $spreadsheet;
    }

    public function toPath(?string $path = null): string
    {
        $targetPath = $path ?? sys_get_temp_dir().'/excel_report_'.bin2hex(random_bytes(8)).'.xlsx';

        $writer = new Xlsx($this->toSpreadsheet());
        $writer->save($targetPath);

        self::$tempFiles[] = $targetPath;

        return $targetPath;
    }

    public function toUploadedFile(string $filename = 'laporan.xlsx'): UploadedFile
    {
        $path = $this->toPath();

        return new UploadedFile(
            path: $path,
            originalName: $filename,
            mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            error: UPLOAD_ERR_OK,
            test: true,
        );
    }

    private function resolveIsGabungan(): bool
    {
        $infoJenis = $this->infoOverrides['JENIS_LAPORAN'] ?? $this->jenisLaporan;

        if (is_string($infoJenis)) {
            $upper = strtoupper(trim($infoJenis));
            if (in_array($upper, ['GABUNGAN', 'AMPERA', 'PAL'], true)) {
                return $upper === 'GABUNGAN';
            }
        }

        return $this->jenisLaporan === 'gabungan';
    }

    /**
     * @return array<int, array{tanggal: int, ampera?: int, pal?: int, total: int}>
     */
    private function generateDailySales(int $count, bool $isGabungan): array
    {
        $rows = [];

        for ($day = 1; $day <= $count; $day++) {
            if ($isGabungan) {
                $ampera = fake()->numberBetween($this->salesMin, $this->salesMax);
                $pal = fake()->numberBetween($this->salesMin, $this->salesMax);
                $rows[] = [
                    'tanggal' => $day,
                    'ampera' => $ampera,
                    'pal' => $pal,
                    'total' => $ampera + $pal,
                ];
            } else {
                $rows[] = [
                    'tanggal' => $day,
                    'total' => fake()->numberBetween($this->salesMin, $this->salesMax),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generateProducts(int $count, string $kategori): array
    {
        $rows = [];
        $prefix = $kategori === 'tertinggi' ? 'TOP' : 'LOW';

        for ($i = 1; $i <= $count; $i++) {
            $hargaJual = fake()->numberBetween(1_000, 250_000);
            $qty = $kategori === 'tertinggi'
                ? fake()->numberBetween(10, 500)
                : fake()->numberBetween(0, 20);

            $rows[] = [
                'kode_brg' => sprintf('%s-%05d', $prefix, $i),
                'nama_brg' => sprintf('Produk %s %d %s', ucfirst($kategori), $i, fake()->word()),
                'satuan' => fake()->randomElement(['PCS', 'KG', 'BOX']),
                'qty' => $qty,
                'harga_jual' => $hargaJual,
                'jumlah' => $hargaJual * $qty,
                'barcode' => sprintf('899%010d', $i + ($kategori === 'tertinggi' ? 100_000 : 500_000)),
                'stok' => fake()->numberBetween(0, 1_000),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generateSuppliers(int $count): array
    {
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $gross = fake()->numberBetween(10_000_000, 500_000_000);
            $net = fake()->numberBetween(1_000_000, $gross);

            $rows[] = [
                'kode_supp' => sprintf('S%04d', $i),
                'nama_supp' => sprintf('PT Supplier %d %s', $i, fake()->companySuffix()),
                'gross_total' => $gross,
                'net_sales_total' => $net,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $infoMap
     * @return array<int, array<int, mixed>>
     */
    private function buildInfoRows(array $infoMap): array
    {
        $rows = [['KETERANGAN', 'NILAI']];

        foreach ($infoMap as $key => $value) {
            if (isset($this->omittedInfoFields[$key])) {
                continue;
            }

            $rows[] = [$key, $value];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{tanggal: int, ampera?: int, pal?: int, total: int}>  $dailySales
     * @return array<int, array<int, mixed>>
     */
    private function buildDailySalesRows(array $dailySales, bool $isGabungan): array
    {
        if ($isGabungan) {
            $rows = [['TANGGAL', 'AMPERA', 'PAL', 'TOTAL_PENJUALAN']];

            foreach ($dailySales as $sale) {
                $rows[] = [
                    $sale['tanggal'],
                    $sale['ampera'] ?? 0,
                    $sale['pal'] ?? 0,
                    $sale['total'],
                ];
            }

            return $rows;
        }

        $rows = [['TANGGAL', 'TOTAL_PENJUALAN']];

        foreach ($dailySales as $sale) {
            $rows[] = [
                $sale['tanggal'],
                $sale['total'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array{transaksi_member: int, transaksi_non_member: int, member_baru: int}  $transaction
     * @return array<int, array<int, mixed>>
     */
    private function buildTransactionRows(array $transaction): array
    {
        return [
            ['TRANSAKSI_MEMBER', 'TRANSAKSI_NON_MEMBER', 'MEMBER_BARU'],
            [
                $transaction['transaksi_member'],
                $transaction['transaksi_non_member'],
                $transaction['member_baru'],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<int, mixed>>
     */
    private function buildProductRows(array $products): array
    {
        $rows = [['KODE_BRG', 'NAMA_BRG', 'SATUAN', 'QTY', 'HRG_JUAL', 'JUMLAH', 'BARCODE', 'STOK']];

        foreach ($products as $product) {
            $rows[] = [
                $product['kode_brg'],
                $product['nama_brg'],
                $product['satuan'],
                $product['qty'],
                $product['harga_jual'],
                $product['jumlah'],
                $product['barcode'],
                $product['stok'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $suppliers
     * @return array<int, array<int, mixed>>
     */
    private function buildSupplierRows(array $suppliers): array
    {
        $rows = [['KODE_SUPP', 'NAMA_SUPP', 'GROSS_TOTAL', 'NET_SALES_TOTAL']];

        foreach ($suppliers as $supplier) {
            $rows[] = [
                $supplier['kode_supp'],
                $supplier['nama_supp'],
                $supplier['gross_total'],
                $supplier['net_sales_total'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $columnsToRemove
     * @return array<int, array<int, mixed>>
     */
    private function removeColumns(array $rows, array $columnsToRemove): array
    {
        $headers = array_map(fn ($header) => strtoupper(trim((string) $header)), $rows[0]);
        $removeIndexes = [];

        foreach ($headers as $index => $header) {
            if (in_array($header, $columnsToRemove, true)) {
                $removeIndexes[] = $index;
            }
        }

        if (empty($removeIndexes)) {
            return $rows;
        }

        $filteredRows = [];

        foreach ($rows as $row) {
            $newRow = [];
            foreach ($row as $index => $cell) {
                if (! in_array($index, $removeIndexes, true)) {
                    $newRow[] = $cell;
                }
            }
            $filteredRows[] = $newRow;
        }

        return $filteredRows;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     */
    private function resolveColumnLetter(array $headerRow, string|int $column): string
    {
        if (is_int($column)) {
            if ($column < 1) {
                throw new InvalidArgumentException('Indeks kolom berbasis angka minimal 1.');
            }

            return Coordinate::stringFromColumnIndex($column);
        }

        $normalizedHeader = strtoupper(trim($column));
        $normalizedHeaders = array_map(fn ($col) => strtoupper(trim((string) $col)), $headerRow);
        $headerIndex = array_search($normalizedHeader, $normalizedHeaders, true);

        if ($headerIndex !== false) {
            return Coordinate::stringFromColumnIndex($headerIndex + 1);
        }

        if (preg_match('/^[A-Z]+$/i', $column) === 1) {
            return strtoupper($column);
        }

        throw new InvalidArgumentException("Kolom '{$column}' tidak ditemukan di header sheet.");
    }
}
