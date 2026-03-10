<?php

namespace Feature\api\v1;

use App\Enums\BulkGroupTaskStatus;
use App\Enums\Role;
use App\Jobs\AttachSimCardsToGroupJob;
use App\Models\BulkGroupTask;
use App\Models\Contract;
use App\Models\SimCard;
use App\Models\SimGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SimGroupControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Role::cases() as $role) {
            \Spatie\Permission\Models\Role::findOrCreate($role->value);
        }
    }

    public function test_client_can_start_attach_sim_cards_job_for_own_contract_group(): void
    {
        Queue::fake();

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

        $simCards = SimCard::factory()
            ->count(3)
            ->create([
                'contract_id' => $contract->id,
            ]);

        $simCardIds = $simCards->pluck('id')->all();

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $client->id)
            ->postJson("/api/v1/sim-groups/$group->id/sim-cards", [
                'sim_card_ids' => $simCardIds,
            ]);

        $response
            ->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
            ])
            ->assertJsonFragment([
                'status' => BulkGroupTaskStatus::PENDING->value,
            ]);

        $this->assertDatabaseCount('bulk_group_tasks', 1);

        /** @var BulkGroupTask $task */
        $task = BulkGroupTask::query()->firstOrFail();

        $this->assertSame($group->id, $task->sim_group_id);
        $this->assertSame($contract->id, $task->contract_id);
        $this->assertSame($client->id, $task->created_by);
        $this->assertSame(3, $task->total_count);
        $this->assertSame(BulkGroupTaskStatus::PENDING, $task->status);

        Queue::assertPushed(AttachSimCardsToGroupJob::class, static function (AttachSimCardsToGroupJob $job) use ($task, $group, $contract, $simCardIds) {
            return $job->taskId === $task->id
                && $job->simGroupId === $group->id
                && $job->userContractId === $contract->id
                && $job->simCardIds === $simCardIds;
        });
    }

    public function test_client_cannot_start_job_for_foreign_contract_group(): void
    {
        Queue::fake();

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

        $simCards = SimCard::factory()
            ->count(2)
            ->create([
                'contract_id' => $clientContract->id,
            ]);

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $client->id)
            ->postJson("/api/v1/sim-groups/$foreignGroup->id/sim-cards", [
                'sim_card_ids' => $simCards->pluck('id')->all(),
            ]);

        $response->assertForbidden();

        $this->assertDatabaseCount('bulk_group_tasks', 0);

        Queue::assertNothingPushed();
    }
}
