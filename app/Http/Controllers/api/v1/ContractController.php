<?php

namespace App\Http\Controllers\api\v1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexContractRequest;
use App\Http\Requests\StoreContractRequest;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index(IndexContractRequest $request): JsonResponse
    {
        $user = $this->user($request);

        abort_unless($user->hasRoleEnum(Role::ADMIN), 403, 'Forbidden');

        $contracts = Contract::query()
            ->orderBy('id')
            ->paginate(
                perPage: $request->integer('per_page', 20)
            );

        return response()->json($contracts);
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $user = $this->user($request);

        abort_unless($user->hasRoleEnum(Role::ADMIN), 403, 'Forbidden');

        $validated = $request->validated();

        $contract = Contract::query()->create($validated);

        return response()->json($contract, 201);
    }
}
