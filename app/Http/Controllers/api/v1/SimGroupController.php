<?php

namespace App\Http\Controllers\api\v1;

use App\Enums\BulkGroupTaskStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttachSimCardsToGroupRequest;
use App\Jobs\AttachSimCardsToGroupJob;
use App\Models\BulkGroupTask;
use App\Models\SimGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

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
}
