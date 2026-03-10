<?php

namespace App\Jobs;

use App\Enums\BulkGroupTaskStatus;
use App\Models\BulkGroupTask;
use App\Models\SimCard;
use DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class AttachSimCardsToGroupJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $taskId,
        public int $simGroupId,
        public array $simCardIds,
        public ?int $userContractId = null,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        /** @var BulkGroupTask $task */
        $task = BulkGroupTask::query()->findOrFail($this->taskId);

        $task->update([
            'status' => BulkGroupTaskStatus::PROCESSING,
            'started_at' => now(),
        ]);

        $processed = 0;
        $success = 0;

        try {
            foreach (array_chunk($this->simCardIds, 1000) as $chunk) {
                $query = SimCard::query()
                    ->select('id')
                    ->whereIn('id', $chunk);

                if ($this->userContractId !== null) {
                    $query->where('contract_id', $this->userContractId);
                }

                $validIds = $query->pluck('id')->all();

                $rows = array_map(
                    fn (int $simCardId) => [
                        'sim_card_id' => $simCardId,
                        'sim_group_id' => $this->simGroupId,
                    ],
                    $validIds
                );

                if ($rows !== []) {
                    DB::table('sim_card_group')->insertOrIgnore($rows);
                }

                $processed += count($chunk);
                $success += count($validIds);

                $task->update([
                    'processed_count' => $processed,
                    'success_count' => $success,
                    'failed_count' => $processed - $success,
                ]);
            }

            $task->update([
                'status' => BulkGroupTaskStatus::COMPLETED,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            BulkGroupTask::query()
                ->whereKey($this->taskId)
                ->update([
                    'status' => BulkGroupTaskStatus::FAILED,
                    'finished_at' => now(),
                ]);

            throw $e;
        }
    }
}
