<?php

namespace Tests\Feature\api\v1;

use App\Enums\Role;
use App\Models\Contract;
use App\Models\SimCard;
use App\Models\SimGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SimCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Role::cases() as $role) {
            \Spatie\Permission\Models\Role::findOrCreate($role->value);
        }
    }

    public function test_client_sees_only_own_sim_cards_with_groups(): void
    {
        /** @var Contract $contractA */
        $contractA = Contract::factory()->create();
        /** @var Contract $contractB */
        $contractB = Contract::factory()->create();

        /** @var User $client */
        $client = User::factory()->create([
            'contract_id' => $contractA->id,
        ]);
        $client->assignRole(Role::CLIENT->value);

        /** @var SimCard $simCardA1 */
        $simCardA1 = SimCard::factory()->create([
            'contract_id' => $contractA->id,
            'number' => '79990000001',
        ]);

        /** @var SimCard $simCardA2 */
        $simCardA2 = SimCard::factory()->create([
            'contract_id' => $contractA->id,
            'number' => '79990000002',
        ]);

        SimCard::factory()->create([
            'contract_id' => $contractB->id,
            'number' => '79990000003',
        ]);

        /** @var SimGroup $group */
        $group = SimGroup::factory()->create([
            'contract_id' => $contractA->id,
            'name' => 'VIP',
        ]);

        $simCardA1->groups()->attach($group->id, ['created_at' => now()]);
        $simCardA2->groups()->attach($group->id, ['created_at' => now()]);

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $client->id)
            ->getJson('/api/v1/sim-cards');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id',
                        'contract_id',
                        'number',
                        'created_at',
                        'updated_at',
                        'groups' => [
                            '*' => [
                                'id',
                                'name',
                            ],
                        ],
                    ],
                ],
                'links',
                'per_page',
                'total',
            ])
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['number' => '79990000001'])
            ->assertJsonFragment(['number' => '79990000002'])
            ->assertJsonFragment(['name' => 'VIP'])
            ->assertJsonMissing(['number' => '79990000003']);
    }

    public function test_client_can_filter_sim_cards_by_group(): void
    {
        /** @var Contract $contract */
        $contract = Contract::factory()->create();

        /** @var User $client */
        $client = User::factory()->create([
            'contract_id' => $contract->id,
        ]);
        $client->assignRole(Role::CLIENT->value);

        /** @var SimGroup $groupA */
        $groupA = SimGroup::factory()->create([
            'contract_id' => $contract->id,
            'name' => 'Group A',
        ]);

        /** @var SimGroup $groupB */
        $groupB = SimGroup::factory()->create([
            'contract_id' => $contract->id,
            'name' => 'Group B',
        ]);

        /** @var SimCard $simCard1 */
        $simCard1 = SimCard::factory()->create([
            'contract_id' => $contract->id,
            'number' => '79991111111',
        ]);

        /** @var SimCard $simCard2 */
        $simCard2 = SimCard::factory()->create([
            'contract_id' => $contract->id,
            'number' => '79992222222',
        ]);

        $simCard1->groups()->attach($groupA->id, ['created_at' => now()]);
        $simCard2->groups()->attach($groupB->id, ['created_at' => now()]);

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $client->id)
            ->getJson('/api/v1/sim-cards?group_id=' . $groupA->id);

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['number' => '79991111111'])
            ->assertJsonMissing(['number' => '79992222222']);
    }

    public function test_admin_can_filter_sim_cards_by_contract(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN->value);

        /** @var Contract $contractA */
        $contractA = Contract::factory()->create();
        /** @var Contract $contractB */
        $contractB = Contract::factory()->create();

        SimCard::factory()->create([
            'contract_id' => $contractA->id,
            'number' => '79993333333',
        ]);

        SimCard::factory()->create([
            'contract_id' => $contractB->id,
            'number' => '79994444444',
        ]);

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $admin->id)
            ->getJson('/api/v1/sim-cards?contract_id=' . $contractA->id);

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['number' => '79993333333'])
            ->assertJsonMissing(['number' => '79994444444']);
    }

    public function test_sim_cards_can_be_found_by_number_prefix(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN->value);

        /** @var Contract $contract */
        $contract = Contract::factory()->create();

        SimCard::factory()->create([
            'contract_id' => $contract->id,
            'number' => '79995551234',
        ]);

        SimCard::factory()->create([
            'contract_id' => $contract->id,
            'number' => '78885551234',
        ]);

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $admin->id)
            ->getJson('/api/v1/sim-cards?search=7999');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['number' => '79995551234'])
            ->assertJsonMissing(['number' => '78885551234']);
    }

    public function test_admin_does_not_receive_groups_in_sim_cards_list(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN->value);

        /** @var Contract $contract */
        $contract = Contract::factory()->create();

        /** @var SimCard $simCard */
        $simCard = SimCard::factory()->create([
            'contract_id' => $contract->id,
            'number' => '79996667788',
        ]);

        /** @var SimGroup $group */
        $group = SimGroup::factory()->create([
            'contract_id' => $contract->id,
            'name' => 'Hidden Group',
        ]);

        $simCard->groups()->attach($group->id, ['created_at' => now()]);

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $admin->id)
            ->getJson('/api/v1/sim-cards');

        $response->assertOk();

        $data = $response->json('data');

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        $this->assertArrayNotHasKey('groups', $data[0]);
    }
}
