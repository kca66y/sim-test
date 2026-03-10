<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\SimGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class SimGroupFactory extends Factory
{
    protected $model = SimGroup::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'contract_id' => Contract::factory(),
        ];
    }
}
