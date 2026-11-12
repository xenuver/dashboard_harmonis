<?php

namespace Database\Factories;

use App\Models\ProdukPenjualan;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

class ProdukPenjualanFactory extends Factory
{
    protected $model = ProdukPenjualan::class;

    public function definition(): array
    {
        return [
            'upload_id' => null,
            'kategori' => 'tertinggi',
            'barcode' => strval(fake()->unique()->numberBetween(100000000, 999999999)),
            'kode_brg' => strval(fake()->unique()->numberBetween(100000000, 999999999)),
            'nama_brg' => fake()->words(3, true),
            'satuan' => fake()->randomElement(['PCS', 'KG']),
            'harga_jual' => fake()->numberBetween(500, 500000),
            'qty' => fake()->numberBetween(-500, 500),
            'jumlah' => function (array $attributes) {
                return $attributes['harga_jual'] * $attributes['qty'];
            },
            'stok' => fake()->optional(0.75)->numberBetween(-1000, 1000),
        ];
    }

    public function upload_id(int $value)
    {
        return $this->state([
            'upload_id' => $value,
        ]);
    }

    public function kategori(string $value)
    {
        return $this->state([
            'kategori' => $value,
        ]);
    }

    public function barcode(string $value)
    {
        return $this->state([
            'barcode' => $value,
        ]);
    }

    public function kode_brg(string $value)
    {
        return $this->state([
            'kode_brg' => $value,
        ]);
    }

    public function nama_brg(string $value)
    {
        return $this->state([
            'nama_brg' => $value,
        ]);
    }

    public function satuan(string $value)
    {
        return $this->state([
            'satuan' => $value,
        ]);
    }

    public function harga_jual(int $value)
    {
        return $this->state([
            'harga_jual' => $value,
        ]);
    }

    public function qty(int $value)
    {
        return $this->state([
            'qty' => $value,
        ]);
    }

    public function stok(?int $value)
    {
        return $this->state([
            'stok' => $value,
        ]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ProdukPenjualan $report) {
            if(!in_array($report->kategori, ['tertinggi', 'terendah'])) {
                throw new InvalidArgumentException(
                    'Kategori hanya menerima tertinggi dan terendah'
                );
            }

            if($report->upload_id === null) {
                throw new InvalidArgumentException(
                    'ID upload harus diisi'
                );
            }    
        
            if($report->harga_jual < 0) {
                throw new InvalidArgumentException(
                    'Harga jual di bawah 0'
                );
            }
        });
    }
}
