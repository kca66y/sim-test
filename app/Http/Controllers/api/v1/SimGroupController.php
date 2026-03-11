<?php

namespace App\Http\Controllers\api\v1;

use App\Enums\BulkGroupTaskStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttachSimCardsToGroupRequest;
use App\Jobs\AttachSimCardsToGroupJob;
use App\Jobs\ImportSimCardsJob;
use App\Models\BulkGroupTask;
use App\Models\SimGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;

class SimGroupController extends Controller
{
    public function attachSimCards(
        AttachSimCardsToGroupRequest $request,
        SimGroup $simGroup
    ): JsonResponse {
        $user = $this->user($request);

        $isAdmin = $user->hasRole(Role::ADMIN->value);
        $isClient = $user->hasRole(Role::CLIENT->value);

        abort_unless($isAdmin || $isClient, 403, 'Forbidden');

        if ($isClient) {
            abort_unless(
                $user->contract_id === $simGroup->contract_id,
                403,
                'Forbidden'
            );
        }

        $simCardIds = $request->validated('sim_card_ids');

        $task = BulkGroupTask::query()->create([
            'id' => (string) Str::uuid(),
            'contract_id' => $simGroup->contract_id,
            'sim_group_id' => $simGroup->id,
            'created_by' => $user->id,
            'status' => BulkGroupTaskStatus::PENDING,
            'total_count' => count($simCardIds),
            'processed_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'payload_path' => null,
        ]);

        AttachSimCardsToGroupJob::dispatch(
            taskId: $task->id,
            simGroupId: $simGroup->id,
            simCardIds: $simCardIds,
            userContractId: $isClient ? $user->contract_id : null,
        );

        return response()->json([
            'job_id' => $task->id,
            'status' => $task->status,
        ], 202);
    }

    /**
     * @throws JsonException
     */
    public function importSimCards(Request $request, SimGroup $simGroup): JsonResponse
    {
        $user = $this->user($request);

        $isAdmin = $user->hasRole(Role::ADMIN->value);
        $isClient = $user->hasRole(Role::CLIENT->value);

        abort_unless($isAdmin || $isClient, 403, 'Forbidden');

        if ($isClient) {
            abort_unless(
                $user->contract_id === $simGroup->contract_id,
                403,
                'Forbidden'
            );
        }

        $hasFile = $request->hasFile('file');
        $hasNumbers = $request->exists('numbers');

        abort_if(! $hasFile && ! $hasNumbers, 422, 'File or array is required');
        abort_if($hasFile && $hasNumbers, 422, 'Use either file or numbers');

        $payloadPath = null;
        $totalCount = 0;

        if ($hasFile) {
            $file = $request->file('file');

            abort_if(! $file || ! $file->isValid(), 422, 'Invalid file');

            $payloadPath = $file->store('sim-imports');
        } else {
            $numbers = $request->input('numbers');

            abort_if(! is_array($numbers), 422, 'Numbers must be an array');

            $numbers = array_map(static function ($n) {
                abort_if(is_array($n) || is_object($n), 422, 'Invalid numbers format');

                return (string) $n;
            }, $numbers);

            $payloadPath = 'sim-imports/'.Str::uuid().'.txt';

            Storage::makeDirectory('sim-imports');

            $handle = fopen(Storage::path($payloadPath), 'wb');
            abort_if($handle === false, 500, 'Cannot create import file');

            try {
                foreach ($numbers as $number) {
                    fwrite($handle, $number.PHP_EOL);
                }
            } finally {
                fclose($handle);
            }

            $totalCount = count($numbers);
        }

        $task = BulkGroupTask::query()->create([
            'id' => (string) Str::uuid(),
            'contract_id' => $simGroup->contract_id,
            'sim_group_id' => $simGroup->id,
            'created_by' => $user->id,
            'status' => BulkGroupTaskStatus::PENDING,
            'total_count' => $totalCount,
            'processed_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'payload_path' => $payloadPath,
        ]);

        ImportSimCardsJob::dispatch(
            taskId: $task->id,
            simGroupId: $simGroup->id,
            contractId: $simGroup->contract_id,
            payloadPath: $payloadPath,
        );

        return response()->json([
            'task_id' => $task->id,
            'status' => $task->status,
        ], 202);
    }
}
