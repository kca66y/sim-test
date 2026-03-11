<?php

namespace App\Jobs;

use App\Enums\BulkGroupTaskStatus;
use App\Models\BulkGroupTask;
use App\Models\SimCard;
use Generator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ImportSimCardsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public string $taskId,
        public int $simGroupId,
        public int $contractId,
        public string $payloadPath,
        public int $chunkSize = 1000,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $task = BulkGroupTask::query()->findOrFail($this->taskId);

        $task->update([
            'status' => BulkGroupTaskStatus::PROCESSING,
        ]);

        $processed = 0;
        $success = 0;
        $failed = 0;

        try {
            foreach ($this->readChunks($this->payloadPath) as $chunk) {
                [$processedChunk, $successChunk, $failedChunk] = $this->processChunk($chunk);

                $processed += $processedChunk;
                $success += $successChunk;
                $failed += $failedChunk;

                $task->update([
                    'total_count' => $processed,
                    'processed_count' => $processed,
                    'success_count' => $success,
                    'failed_count' => $failed,
                ]);
            }

            $task->update([
                'status' => BulkGroupTaskStatus::COMPLETED,
                'total_count' => $processed,
                'processed_count' => $processed,
                'success_count' => $success,
                'failed_count' => $failed,
            ]);
        } catch (Throwable $e) {
            $task->update([
                'status' => BulkGroupTaskStatus::FAILED,
                'total_count' => $processed,
                'processed_count' => $processed,
                'success_count' => $success,
                'failed_count' => $failed,
            ]);

            throw $e;
        }
    }

    private function readChunks(string $path): Generator
    {
        $absolutePath = Storage::path($path);

        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Cannot open file: $path");
        }

        $chunk = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $chunk[] = $line;

                if (count($chunk) >= $this->chunkSize) {
                    yield $chunk;
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                yield $chunk;
            }
        } finally {
            fclose($handle);
        }
    }

    private function processChunk(array $lines): array
    {
        $processed = count($lines);

        if ($processed === 0) {
            return [0, 0, 0];
        }

        $numbers = [];
        $seen = [];

        foreach ($lines as $line) {
            $number = $this->normalizeNumber($line);

            if ($number === null) {
                continue;
            }

            if (isset($seen[$number])) {
                continue;
            }

            $seen[$number] = true;
            $numbers[] = $number;
        }

        $validUniqueCount = count($numbers);
        $failed = $processed - $validUniqueCount;

        if ($numbers === []) {
            return [$processed, 0, $failed];
        }

        $now = now();

        $simCardRows = array_map(
            fn (string $number) => [
                'contract_id' => $this->contractId,
                'number' => $number,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $numbers
        );

        SimCard::insertOrIgnore($simCardRows);

        $simCards = SimCard::query()
            ->select(['id', 'number'])
            ->where('contract_id', $this->contractId)
            ->whereIn('number', $numbers)
            ->get();

        $pivotRows = [];

        foreach ($simCards as $simCard) {
            $pivotRows[] = [
                'sim_card_id' => $simCard->id,
                'sim_group_id' => $this->simGroupId,
            ];
        }

        if ($pivotRows !== []) {
            DB::table('sim_card_group')->insertOrIgnore($pivotRows);
        }

        return [$processed, $validUniqueCount, $failed];
    }

    private function normalizeNumber(string $value): ?string
    {
        $number = preg_replace('/\D+/', '', trim($value));

        if ($number === null || $number === '') {
            return null;
        }

        if (strlen($number) === 11 && str_starts_with($number, '8')) {
            $number = '7'.substr($number, 1);
        }

        if (strlen($number) < 10 || strlen($number) > 15) {
            return null;
        }

        return $number;
    }
}
