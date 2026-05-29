<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'icon', 'resources_count', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function resources(): HasMany
    {
        return $this->hasMany(CareerResource::class, 'category_id');
    }
}
