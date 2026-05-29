<?php

namespace App\Models;

use App\Models\Department as DepartmentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class FacultyUser extends Authenticatable
{
    use SoftDeletes;

    protected $fillable = [
        'sso_id', 'email', 'name', 'role', 'department',
        'permissions', 'profile_picture', 'is_active', 'bio',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'author_id');
    }

    public function communicationBoards(): HasMany
    {
        return $this->hasMany(CommunicationBoard::class, 'creator_id');
    }

    public function boardPosts(): HasMany
    {
        return $this->hasMany(BoardPost::class, 'author_id');
    }

    public function boardComments(): HasMany
    {
        return $this->hasMany(BoardComment::class, 'author_id');
    }

    public function careerResources(): HasMany
    {
        return $this->hasMany(CareerResource::class, 'author_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }

    public function messageThreads(): HasMany
    {
        return $this->hasMany(MessageThread::class, 'creator_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function departmentRecord(): ?DepartmentModel
    {
        if (! $this->department) {
            return null;
        }

        return DepartmentModel::where('code', $this->department)->first();
    }

    public function departmentId(): ?int
    {
        return $this->departmentRecord()?->id;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        $permissions = $this->permissions ?? [];

        return in_array($permission, $permissions, true);
    }

    public function isBlocked(): bool
    {
        return $this->role === 'student' || ! $this->is_active;
    }

    public function canPublishAnnouncements(): bool
    {
        return in_array($this->role, ['admin', 'instructor', 'admission_officer'], true);
    }

    public function canManageBoards(): bool
    {
        return in_array($this->role, ['admin', 'instructor', 'admission_officer'], true);
    }

    public function canUploadResources(): bool
    {
        return in_array($this->role, ['admin', 'instructor', 'librarian'], true);
    }
}
