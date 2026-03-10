<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (Role::cases() as $role) {
            \Spatie\Permission\Models\Role::findOrCreate($role->value);
        }

        $contract = Contract::query()->create([
            'name' => 'Main contract',
        ]);

        /** @var User $admin */
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
        ]);

        $admin->assignRole(Role::ADMIN->value);

        /** @var User $client */
        $client = User::factory()->create([
            'email' => 'client@test.com',
            'contract_id' => $contract->id,
        ]);

        $client->assignRole(Role::CLIENT->value);
    }
}
