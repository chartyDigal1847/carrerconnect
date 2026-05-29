<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerResource extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'description', 'category_id', 'author_id', 'resource_type',
        'file_path', 'external_url', 'thumbnail', 'downloads_count',
        'views_count', 'is_featured', 'is_approved'
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'is_approved' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ResourceCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(FacultyUser::class, 'author_id');
    }

    public function incrementDownloads(): void
    {
        $this->increment('downloads_count');
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }
}
