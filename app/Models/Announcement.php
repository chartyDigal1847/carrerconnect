<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'content', 'author_id', 'department_id', 'priority',
        'visibility', 'target_roles', 'published_at', 'expires_at',
        'views_count', 'is_pinned', 'is_active',
    ];

    protected $casts = [
        'target_roles' => 'array',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_pinned' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(FacultyUser::class, 'author_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    public function canAccess(FacultyUser $user): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($this->visibility === 'all') {
            return true;
        }

        if ($this->visibility === 'department' && $this->department_id) {
            $dept = $this->department;
            return $dept && $user->department == $dept->code;
        }

        if ($this->visibility === 'role' && $this->target_roles) {
            return in_array($user->role, $this->target_roles);
        }

        return false;
    }
}
