<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationBoard extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'description', 'creator_id', 'department_id',
        'visibility', 'allowed_roles', 'is_moderated', 'is_active', 'posts_count'
    ];

    protected $casts = [
        'allowed_roles' => 'array',
        'is_moderated' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(FacultyUser::class, 'creator_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BoardPost::class, 'board_id');
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

        if ($this->visibility === 'role' && $this->allowed_roles) {
            return in_array($user->role, $this->allowed_roles);
        }

        return false;
    }
}
