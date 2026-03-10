<?php

namespace Tests\Feature\api\v1;

use App\Enums\BulkGroupTaskStatus;
use App\Enums\Role;
use App\Models\BulkGroupTask;
use App\Models\Contract;
use App\Models\SimGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Str;
use Tests\TestCase;

class BulkGroupTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Role::cases() as $role) {
            \Spatie\Permission\Models\Role::findOrCreate($role->value);
        }
    }

    public function test_admin_can_view_task_status(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN->value);

        /** @var Contract $contract */
        $contract = Contract::factory()->create();

        /** @var SimGroup $group */
        $group = SimGroup::factory()->create([
            'contract_id' => $contract->id,
        ]);

        /** @var User $creator */
        $creator = User::factory()->create();

        /** @var BulkGroupTask $task */
        $task = BulkGroupTask::query()->create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'sim_group_id' => $group->id,
            'created_by' => $creator->id,
            'status' => BulkGroupTaskStatus::PROCESSING,
            'total_count' => 10,
            'processed_count' => 5,
            'success_count' => 5,
            'failed_count' => 0,
        ]);

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $admin->id)
            ->getJson("/api/v1/bulk-group-tasks/$task->id");

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $task->id,
                'status' => BulkGroupTaskStatus::PROCESSING->value,
                'contract_id' => $contract->id,
                'sim_group_id' => $group->id,
                'created_by' => $creator->id,
                'total_count' => 10,
                'processed_count' => 5,
                'success_count' => 5,
                'failed_count' => 0,
            ]);
    }

    public function test_client_can_view_own_contract_task_status(): void
    {
        /** @var Contract $contract */
        $contract = Contract::factory()->create();

        /** @var User $client */
        $client = User::factory()->create([
            'contract_id' => $contract->id,
        ]);
        $client->assignRole(Role::CLIENT->value);

        /** @var SimGroup $group */
        $group = SimGroup::factory()->create([
            'contract_id' => $contract->id,
        ]);

        /** @var User $creator */
        $creator = User::factory()->create([
            'contract_id' => $contract->id,
        ]);

        /** @var BulkGroupTask $task */
        $task = BulkGroupTask::query()->create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'sim_group_id' => $group->id,
            'created_by' => $creator->id,
            'status' => BulkGroupTaskStatus::PENDING,
            'total_count' => 3,
            'processed_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
        ]);

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $client->id)
            ->getJson("/api/v1/bulk-group-tasks/$task->id");

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $task->id,
                'status' => BulkGroupTaskStatus::PENDING->value,
                'contract_id' => $contract->id,
            ]);
    }

    public function test_client_cannot_view_foreign_contract_task_status(): void
    {
        /** @var Contract $clientContract */
        $clientContract = Contract::factory()->create();

        /** @var Contract $foreignContract */
        $foreignContract = Contract::factory()->create();

        /** @var User $client */
        $client = User::factory()->create([
            'contract_id' => $clientContract->id,
        ]);
        $client->assignRole(Role::CLIENT->value);

        /** @var SimGroup $foreignGroup */
        $foreignGroup = SimGroup::factory()->create([
            'contract_id' => $foreignContract->id,
        ]);

        /** @var User $creator */
        $creator = User::factory()->create();

        /** @var BulkGroupTask $task */
        $task = BulkGroupTask::query()->create([
            'id' => (string) Str::uuid(),
            'contract_id' => $foreignContract->id,
            'sim_group_id' => $foreignGroup->id,
            'created_by' => $creator->id,
            'status' => BulkGroupTaskStatus::PENDING,
            'total_count' => 7,
            'processed_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
        ]);

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $client->id)
            ->getJson("/api/v1/bulk-group-tasks/$task->id");

        $response->assertForbidden();
    }

    public function test_request_without_test_user_header_is_unauthorized(): void
    {
        /** @var Contract $contract */
        $contract = Contract::factory()->create();

        /** @var SimGroup $group */
        $group = SimGroup::factory()->create([
            'contract_id' => $contract->id,
        ]);

        /** @var User $creator */
        $creator = User::factory()->create();

        /** @var BulkGroupTask $task */
        $task = BulkGroupTask::query()->create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'sim_group_id' => $group->id,
            'created_by' => $creator->id,
            'status' => BulkGroupTaskStatus::PENDING,
            'total_count' => 1,
            'processed_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
        ]);

        $response = $this->getJson("/api/v1/bulk-group-tasks/$task->id");

        $response->assertUnauthorized();
    }
}
