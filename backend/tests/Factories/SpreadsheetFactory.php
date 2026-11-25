namespace Tests\Factories;

use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SpreadsheetFactory
{
    protected array $headers = [];
    protected array $rows = [];

    public function headers(array $headers): static
    {
        $this->headers = $headers;

        return $this;
    }

    public function rows(array $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function make(string $filename = 'test.xlsx'): string
    {
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();

        $data = array_merge(
            [$this->headers],
            $this->rows
        );

        $sheet->fromArray($data);

        $path = storage_path("app/testing/{$filename}");

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}