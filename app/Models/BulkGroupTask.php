<?php

namespace App\Models;

use App\Enums\BulkGroupTaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkGroupTask extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'contract_id',
        'sim_group_id',
        'created_by',
        'status',
        'total_count',
        'processed_count',
        'success_count',
        'failed_count',
        'payload_path',
        'started_at',
        'finished_at'
    ];

    protected $casts = [
        'status' => BulkGroupTaskStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function simGroup(): BelongsTo
    {
        return $this->belongsTo(SimGroup::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
