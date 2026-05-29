<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id',
        'changes', 'ip_address'
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(FacultyUser::class, 'user_id');
    }

    /**
     * Convenience static helper used throughout the codebase.
     * Logs a human-readable action string with an optional colour tag.
     */
    public static function record(string $message, string $color = 'blue'): void
    {
        static::create([
            'user_id'     => Auth::guard('faculty')->id(),
            'action'      => $message,
            'entity_type' => $color,   // repurposed as a colour/category tag
            'ip_address'  => request()->ip(),
        ]);
    }
}
