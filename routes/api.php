<?php

use App\Http\Controllers\api\v1\BulkGroupTaskController;
use App\Http\Controllers\api\v1\ContractController;
use App\Http\Controllers\api\v1\SimCardController;
use App\Http\Controllers\api\v1\SimGroupController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('resolve.test.user')
    ->group(function () {
        Route::get('contracts', [ContractController::class, 'index']);
        Route::post('contracts', [ContractController::class, 'store']);

        Route::get('sim-cards', [SimCardController::class, 'index']);

        Route::post('sim-groups/{simGroup}/sim-cards', [SimGroupController::class, 'attachSimCards']);

        Route::get('bulk-group-tasks/{task}', [BulkGroupTaskController::class, 'show']);

        Route::post(
            'sim-groups/{simGroup}/sim-cards/import', [SimGroupController::class, 'importSimCards']
        );
    });
