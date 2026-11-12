<?php

namespace Database\Factories;

use App\Models\SupplierPenjualan;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

class SupplierPenjualanFactory extends Factory
{
    protected $model = SupplierPenjualan::class;

    public function definition(): array
    {
        return [
            'upload_id' => null,
            'kode_supp' => 'S'.str_pad(fake()->numberBetween(1, 99999), 4, '0', STR_PAD_LEFT),
            'nama_supp' => fake()->unique()->company(),
            'gross_total' => fake()->numberBetween(100000000, 999999999),
            'net_sales_total' => function (array $attributes) {
                return fake()->numberBetween(
                    100000000,
                    max(100000000, $attributes['gross_total'] - 1)
                );
            },
        ];
    }

    public function upload_id(int $value)
    {
        return $this->state([
            'upload_id' => $value,
        ]);
    }

    public function kode_supp(string $value)
    {
        return $this->state([
            'kode_supp' => $value,
        ]);
    }

    public function nama_supp(string $value)
    {
        return $this->state([
            'nama_supp' => $value,
        ]);
    }

    public function gross_total(int $value)
    {
        return $this->state([
            'gross_total' => $value,
        ]);
    }

    public function net_sales_total(int $value)
    {
        return $this->state([
            'net_sales_total' => $value,
        ]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SupplierPenjualan $report) {
            if($report->upload_id === null) {
                throw new InvalidArgumentException(
                    'ID upload harus diisi'
                );
            }
        });
    }
}
