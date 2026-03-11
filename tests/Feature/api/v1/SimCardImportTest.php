<?php

namespace Feature\api\v1;

use App\Enums\Role;
use App\Jobs\ImportSimCardsJob;
use App\Models\BulkGroupTask;
use App\Models\Contract;
use App\Models\SimGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SimCardImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Role::cases() as $role) {
            \Spatie\Permission\Models\Role::findOrCreate($role->value);
        }
    }

    public function test_import_sim_cards_from_array(): void
    {
        Storage::fake('local');
        Queue::fake();

        $contract = Contract::factory()->create();

        $client = User::factory()->create([
            'contract_id' => $contract->id,
        ]);

        $client->assignRole(Role::CLIENT->value);

        $simGroup = SimGroup::factory()->create([
            'contract_id' => $contract->id,
        ]);

        $numbers = [
            '79990000001',
            '79990000002',
            '79990000003',
        ];

        $response = $this
            ->withHeader('X-Test-User-Id', (string) $client->id)
            ->postJson("/api/v1/sim-groups/$simGroup->id/sim-cards/import", [
                'numbers' => $numbers,
            ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'task_id',
                'status',
            ]);

        $this->assertDatabaseCount('bulk_group_tasks', 1);

        $task = BulkGroupTask::first();

        Storage::disk('local')->assertExists($task->payload_path);

        Queue::assertPushed(ImportSimCardsJob::class, static function ($job) use ($simGroup, $contract, $task) {
            return $job->taskId === $task->id
                && $job->simGroupId === $simGroup->id
                && $job->contractId === $contract->id;
        });
    }
}
