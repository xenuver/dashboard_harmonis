<?php

namespace Database\Factories;

use App\Models\SalesHarian;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class SalesHarianFactory extends Factory
{
    protected $model = SalesHarian::class;

    public function definition(): array
    {
        return [
            'upload_id' => null,
            'cabang_id' => null,
            'tanggal' => null,
            'total_penjualan' => fake()->optional(0.9, 0)->numberBetween(100000000, 999999999),
        ];
    }

    public function upload_id(int $value)
    {
        return $this->state([
            'upload_id' => $value,
        ]);
    }

    public function cabang_id(string $value)
    {
        return $this->state([
            'cabang_id' => $value,
        ]);
    }

    public function tanggal(Carbon $value)
    {
        return $this->state([
            'tanggal' => $value->toDateTime(),
        ]);
    }

    public function total_penjualan(int $value)
    {
        return $this->state([
            'total_penjualan' => $value,
        ]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SalesHarian $report) {
            if($report->upload_id === null) {
                throw new InvalidArgumentException(
                    'ID upload harus diisi'
                );
            }

            if($report->cabang_id === null) {
                throw new InvalidArgumentException(
                    'ID cabang harus diisi'
                );
            }

            if($report->tanggal === null) {
                throw new InvalidArgumentException(
                    'Tanggal harus diisi'
                );
            }
        
            if($report->total_penjualan < 0) {
                throw new InvalidArgumentException(
                    'Total penjualan di bawah 0'
                );
            }
        });
    }
}
