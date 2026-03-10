<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\SimCard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class SimCardFactory extends Factory
{
    protected $model = SimCard::class;

    public function definition(): array
    {
        return [
            'number' => '79' . $this->faker->unique()->numerify('#########'),
            'contract_id' => Contract::factory(),
        ];
    }

    public function forContract(int $contractId): static
    {
        return $this->state(fn () => [
            'contract_id' => $contractId,
        ]);
    }
}
