<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SimGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'name'
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function simCards(): BelongsToMany
    {
        return $this->belongsToMany(
            SimCard::class,
            'sim_card_group',
            'sim_group_id',
            'sim_card_id'
        )->using(SimCardGroup::class);
    }
}
