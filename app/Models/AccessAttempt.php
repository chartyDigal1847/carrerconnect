<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'faculty_user_id', 'sso_id', 'role_attempted', 'reason',
        'endpoint', 'method', 'ip_address', 'user_agent', 'was_blocked', 'created_at',
    ];

    protected $casts = [
        'was_blocked' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->created_at ??= now();
        });
    }

    public function facultyUser(): BelongsTo
    {
        return $this->belongsTo(FacultyUser::class);
    }
}
