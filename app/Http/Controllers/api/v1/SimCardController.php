<?php

namespace App\Http\Controllers\api\v1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexSimCardRequest;
use App\Models\SimCard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class SimCardController extends Controller
{
    public function index(IndexSimCardRequest $request): JsonResponse
    {
        $user = $this->user($request);

        $query = SimCard::query();

        if ($user->hasRole(Role::CLIENT->value)) {
            abort_unless($user->contract_id, 403, 'Client has no contract');

            $query
                ->where('contract_id', $user->contract_id)
                ->with(['groups:id,name']);

            if ($request->filled('group_id')) {
                $groupId = $request->integer('group_id');

                $query->whereHas('groups', function (Builder $builder) use ($groupId) {
                    $builder->where('sim_groups.id', $groupId);
                });
            }
        }

        if ($user->hasRole(Role::ADMIN->value) && $request->filled('contract_id')) {
            $query->where('contract_id', $request->integer('contract_id'));
        }

        if ($request->filled('search')) {
            $search = preg_replace('/\D+/', '', $request->string('search')->value());

            if ($search !== '') {
                $query->where('number', 'like', $search . '%');
            }
        }

        match ($request->input('sort', 'id_desc')) {
            'id_asc' => $query->orderBy('id'),
            'number_asc' => $query->orderBy('number'),
            'number_desc' => $query->orderByDesc('number'),
            default => $query->orderByDesc('id'),
        };

        $simCards = $query->paginate($request->integer('per_page', 20));

        return response()->json($simCards);
    }


}
