<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SimCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'number',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(
            SimGroup::class,
            'sim_card_group',
            'sim_card_id',
            'sim_group_id'
        )->using(SimCardGroup::class);
    }
}
