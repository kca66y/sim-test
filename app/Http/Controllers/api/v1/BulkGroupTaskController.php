<?php

namespace App\Http\Controllers\api\v1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\BulkGroupTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BulkGroupTaskController extends Controller
{
    public function show(Request $request, BulkGroupTask $task): JsonResponse
    {
        $user = $this->user($request);

        $isAdmin = $user->hasRole(Role::ADMIN->value);
        $isClient = $user->hasRole(Role::CLIENT->value);

        abort_unless($isAdmin || $isClient, 403, 'Forbidden');

        if ($isClient) {
            abort_unless(
                $user->contract_id === $task->contract_id,
                403,
                'Forbidden'
            );
        }

        return response()->json([
            'id' => $task->id,
            'status' => $task->status,
            'contract_id' => $task->contract_id,
            'sim_group_id' => $task->sim_group_id,
            'created_by' => $task->created_by,
            'total_count' => $task->total_count,
            'processed_count' => $task->processed_count,
            'success_count' => $task->success_count,
            'failed_count' => $task->failed_count,
            'started_at' => $task->started_at,
            'finished_at' => $task->finished_at,
            'created_at' => $task->created_at,
        ]);
    }
}
