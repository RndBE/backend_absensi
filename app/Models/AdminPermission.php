<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminPermission extends Model
{
    protected $fillable = [
        'key',
        'group',
        'name',
        'description',
    ];

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(AdminRolePermission::class);
    }
}
