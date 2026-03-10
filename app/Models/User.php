<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Traits\HasRoles;

class User extends Model
{
    use HasFactory, HasRoles;

    protected $fillable = [
        'contract_id',
        'name',
        'email',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function hasRoleEnum(Role $role): bool
    {
        return $this->hasRole($role->value);
    }
}
