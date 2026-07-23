<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = ['name', 'logo', 'address', 'phone', 'email', 'npwp'];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function regulations(): HasMany
    {
        return $this->hasMany(CompanyRegulation::class);
    }

    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }
}
