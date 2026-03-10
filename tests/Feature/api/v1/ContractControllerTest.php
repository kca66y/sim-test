<?php

namespace Tests\Feature\api\v1;

use App\Enums\Role;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class ContractControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Role::cases() as $role) {
            SpatieRole::findOrCreate($role->value);
        }
    }

    public function test_admin_can_get_contracts_list(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN->value);

        Contract::factory()->count(3)->create();

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $admin->id)
            ->getJson('/api/v1/contracts');
        $response
            ->assertOk()
            ->assertJsonStructure([
                'current_page',
                'data',
                'links',
                'per_page',
                'total',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_client_cannot_get_contracts_list(): void
    {
        /** @var Contract $contract */
        $contract = Contract::factory()->create();

        /** @var User $client */
        $client = User::factory()->create([
            'contract_id' => $contract->id,
        ]);

        $client->assignRole(Role::CLIENT->value);

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $client->id)
            ->getJson('/api/v1/contracts');

        $response->assertForbidden();
    }

    public function test_admin_can_store_contract(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN->value);

        $payload = [
            'name' => 'Main contract',
        ];

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $admin->id)
            ->postJson('/api/v1/contracts', $payload);

        $response
            ->assertCreated()
            ->assertJsonFragment([
                'name' => 'Main contract',
            ]);

        $this->assertDatabaseHas('contracts', $payload);
    }

    public function test_client_cannot_store_contract(): void
    {
        /** @var Contract $contract */
        $contract = Contract::factory()->create();

        /** @var User $client */
        $client = User::factory()->create([
            'contract_id' => $contract->id,
        ]);
        $client->assignRole(Role::CLIENT->value);

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $client->id)
            ->postJson('/api/v1/contracts', [
                'name' => 'Forbidden contract',
            ]);

        $response->assertForbidden();
    }
}
