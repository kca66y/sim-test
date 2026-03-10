<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SimCardGroup extends Pivot
{
    protected $table = 'sim_card_group';

    protected $fillable = [
        'sim_card_id',
        'sim_group_id'
    ];

    public $timestamps = false;
}
