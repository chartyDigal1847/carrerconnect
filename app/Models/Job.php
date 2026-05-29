<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    protected $table = 'career_jobs';

    protected $fillable = [
        'title',
        'company_name',
        'location',
        'job_type',
        'work_setup',
        'description',
        'status',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
