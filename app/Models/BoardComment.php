<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'post_id', 'author_id', 'content', 'parent_id',
        'is_moderated', 'status'
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BoardPost::class, 'post_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(FacultyUser::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(BoardComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(BoardComment::class, 'parent_id');
    }
}
