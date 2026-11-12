<?php

namespace Database\Factories;

use App\Models\MemberCount;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

class MemberCountFactory extends Factory
{
    protected $model = MemberCount::class;

    public function definition(): array
    {
        return [
            'upload_id' => null,
            'member_baru' => fake()->numberBetween(0, PHP_INT_MAX),
        ];
    }

    public function upload_id(int $value) {
        return $this->state([
            'upload_id' => $value,
        ]);
    }

    public function member_baru(string $value) {
        return $this->state([
            'member_baru' => $value,
        ]);
    }

    public function configure(): static
    {
        return $this->afterMaking(function (MemberCount $report) {
            if($report->upload_id === null) {
                throw new InvalidArgumentException(
                    'ID upload harus diisi'
                );
            }    
        
            if($report->member_baru < 0) {
                throw new InvalidArgumentException(
                    'Member baru di bawah 0'
                );
            }
        });
    }
}
