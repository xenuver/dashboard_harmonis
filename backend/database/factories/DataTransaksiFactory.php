<?php

namespace Database\Factories;

use App\Models\DataTransaksi;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

class DataTransaksiFactory extends Factory
{
    protected $model = DataTransaksi::class;

    public function definition(): array
    {
        return [
            'upload_id' => null,
            'transaksi_member' => fake()->numberBetween(0, 1000000),
            'transaksi_non_member' => fake()->numberBetween(0, 1000000),
        ];
    }

    public function upload_id(string $value)
    {
        return $this->state([
            'upload_id' => $value,
        ]);
    }

    public function transaki_member(string $value)
    {
        return $this->state([
            'transaki_member' => $value,
        ]);
    }

    public function transaki_non_member(string $value)
    {
        return $this->state([
            'transaki_non_member' => $value,
        ]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (DataTransaksi $report) {
            if ($report->upload_id === null) {
                throw new InvalidArgumentException(
                    'ID upload harus diisi'
                );
            }

            if ($report->transaki_member < 0) {
                throw new InvalidArgumentException(
                    'Transaki member di bawah 0'
                );
            }

            if ($report->transaki_non_member < 0) {
                throw new InvalidArgumentException(
                    'Transaki non-member di bawah 0'
                );
            }
        });
    }
}
