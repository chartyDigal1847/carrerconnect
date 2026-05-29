<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Internship extends Model
{
    protected $fillable = [
        'title',
        'company_name',
        'location',
        'work_setup',
        'duration_weeks',
        'description',
        'status',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
