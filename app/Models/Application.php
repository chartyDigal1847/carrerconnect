<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    protected $fillable = [
        'student_name',
        'email',
        'phone',
        'resume',
        'job_id',
        'internship_id',
        'position',
        'status',
        'date_applied',
        'submitted_by',
        'notes',
    ];

    protected $casts = [
        'date_applied' => 'date',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function internship(): BelongsTo
    {
        return $this->belongsTo(Internship::class);
    }
}
