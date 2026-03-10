<?php

namespace Tests\Feature\Jobs;

use App\Enums\BulkGroupTaskStatus;
use App\Jobs\AttachSimCardsToGroupJob;
use App\Models\BulkGroupTask;
use App\Models\Contract;
use App\Models\SimCard;
use App\Models\SimGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Str;
use Tests\TestCase;
use Throwable;

class AttachSimCardsToGroupJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws Throwable
     */
    public function test_job_attaches_sim_cards_to_group_and_completes_task(): void
    {
        /** @var Contract $contract */
        $contract = Contract::factory()->create();

        /** @var SimGroup $group */
        $group = SimGroup::factory()->create([
            'contract_id' => $contract->id,
        ]);

        /** @var User $creator */
        $creator = User::factory()->create();

        $simCards = SimCard::factory()
            ->count(3)
            ->create([
                'contract_id' => $contract->id,
            ]);

        $simCardIds = $simCards->pluck('id')->all();

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

        $job = new AttachSimCardsToGroupJob(
            taskId: $task->id,
            simGroupId: $group->id,
            simCardIds: $simCardIds,
            userContractId: $contract->id,
        );

        $job->handle();

        $task->refresh();

        $this->assertSame(BulkGroupTaskStatus::COMPLETED, $task->status);
        $this->assertSame(3, $task->processed_count);
        $this->assertSame(3, $task->success_count);
        $this->assertSame(0, $task->failed_count);
        $this->assertNotNull($task->started_at);
        $this->assertNotNull($task->finished_at);

        foreach ($simCards as $simCard) {
            /** @var SimCard $simCard */
            $this->assertDatabaseHas('sim_card_group', [
                'sim_card_id' => $simCard->id,
                'sim_group_id' => $group->id,
            ]);
        }
    }

    /**
     * @throws Throwable
     */
    public function test_job_skips_foreign_contract_sim_cards_for_client_context(): void
    {
        /** @var Contract $clientContract */
        $clientContract = Contract::factory()->create();

        /** @var Contract $foreignContract */
        $foreignContract = Contract::factory()->create();

        /** @var SimGroup $group */
        $group = SimGroup::factory()->create([
            'contract_id' => $clientContract->id,
        ]);

        /** @var User $creator */
        $creator = User::factory()->create([
            'contract_id' => $clientContract->id,
        ]);

        /** @var SimCard $allowedSimCard */
        $allowedSimCard = SimCard::factory()->create([
            'contract_id' => $clientContract->id,
            'number' => '79990000001',
        ]);

        /** @var SimCard $foreignSimCard */
        $foreignSimCard = SimCard::factory()->create([
            'contract_id' => $foreignContract->id,
            'number' => '79990000002',
        ]);

        /** @var BulkGroupTask $task */
        $task = BulkGroupTask::query()->create([
            'id' => (string) Str::uuid(),
            'contract_id' => $clientContract->id,
            'sim_group_id' => $group->id,
            'created_by' => $creator->id,
            'status' => BulkGroupTaskStatus::PENDING,
            'total_count' => 2,
            'processed_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
        ]);

        $job = new AttachSimCardsToGroupJob(
            taskId: $task->id,
            simGroupId: $group->id,
            simCardIds: [$allowedSimCard->id, $foreignSimCard->id],
            userContractId: $clientContract->id,
        );

        $job->handle();

        $task->refresh();

        $this->assertSame(BulkGroupTaskStatus::COMPLETED, $task->status);
        $this->assertSame(2, $task->processed_count);
        $this->assertSame(1, $task->success_count);
        $this->assertSame(1, $task->failed_count);

        $this->assertDatabaseHas('sim_card_group', [
            'sim_card_id' => $allowedSimCard->id,
            'sim_group_id' => $group->id,
        ]);

        $this->assertDatabaseMissing('sim_card_group', [
            'sim_card_id' => $foreignSimCard->id,
            'sim_group_id' => $group->id,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function test_job_does_not_duplicate_existing_pivot_rows(): void
    {
        /** @var Contract $contract */
        $contract = Contract::factory()->create();

        /** @var SimGroup $group */
        $group = SimGroup::factory()->create([
            'contract_id' => $contract->id,
        ]);

        /** @var User $creator */
        $creator = User::factory()->create();

        /** @var SimCard $simCard */
        $simCard = SimCard::factory()->create([
            'contract_id' => $contract->id,
        ]);

        $simCard->groups()->attach($group->id, ['created_at' => now()]);

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

        $job = new AttachSimCardsToGroupJob(
            taskId: $task->id,
            simGroupId: $group->id,
            simCardIds: [$simCard->id],
            userContractId: $contract->id,
        );

        $job->handle();

        $task->refresh();

        $this->assertSame(BulkGroupTaskStatus::COMPLETED, $task->status);
        $this->assertSame(1, $task->processed_count);
        $this->assertSame(1, $task->success_count);
        $this->assertSame(0, $task->failed_count);

        $this->assertDatabaseCount('sim_card_group', 1);
    }
}
