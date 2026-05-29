<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'board_id', 'author_id', 'title', 'content', 'is_pinned',
        'is_moderated', 'status', 'comments_count', 'views_count'
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_moderated' => 'boolean',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(CommunicationBoard::class, 'board_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(FacultyUser::class, 'author_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BoardComment::class, 'post_id');
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }
}
