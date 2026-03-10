<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function simCards(): HasMany
    {
        return $this->hasMany(SimCard::class);
    }

    public function simGroups(): HasMany
    {
        return $this->hasMany(SimGroup::class);
    }
}
