<?php

namespace Tests\Feature\Jobs;

use App\Enums\BulkGroupTaskStatus;
use App\Jobs\ImportSimCardsJob;
use App\Models\BulkGroupTask;
use App\Models\Contract;
use App\Models\SimCard;
use App\Models\SimGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Str;
use Tests\TestCase;
use Throwable;

class ImportSimCardJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws Throwable
     */
    public function test_job_imports_sim_cards_and_attaches_to_group(): void
    {
        Storage::fake('local');

        $contract = Contract::factory()->create();

        $user = User::factory()->create([
            'contract_id' => $contract->id,
        ]);

        $group = SimGroup::factory()->create([
            'contract_id' => $contract->id,
        ]);

        $task = BulkGroupTask::create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'sim_group_id' => $group->id,
            'created_by' => $user->id,
            'status' => BulkGroupTaskStatus::PENDING,
            'total_count' => 0,
            'processed_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'payload_path' => '',
        ]);

        $filePath = 'sim-imports/test.txt';

        Storage::disk('local')->put($filePath, implode("\n", [
            '79990000001',
            '79990000002',
            '+7 (999) 0000003',
            'invalid-number',
            '79990000001',
        ]));

        $job = new ImportSimCardsJob(
            taskId: $task->id,
            simGroupId: $group->id,
            contractId: $contract->id,
            payloadPath: $filePath,
            chunkSize: 2
        );

        $job->handle();

        $this->assertEquals(
            ['79990000001', '79990000002', '79990000003'],
            SimCard::pluck('number')->sort()->values()->all()
        );

        $this->assertDatabaseHas('sim_cards', [
            'number' => '79990000001',
            'contract_id' => $contract->id,
        ]);

        $this->assertDatabaseHas('sim_cards', [
            'number' => '79990000002',
            'contract_id' => $contract->id,
        ]);

        $this->assertDatabaseHas('sim_cards', [
            'number' => '79990000003',
            'contract_id' => $contract->id,
        ]);

        $this->assertDatabaseCount('sim_card_group', 3);

        $task->refresh();

        $this->assertEquals(BulkGroupTaskStatus::COMPLETED, $task->status);
        $this->assertEquals(5, $task->processed_count);
        $this->assertEquals(4, $task->success_count);
        $this->assertEquals(1, $task->failed_count);
    }
}
