<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentActivityLog extends Model
{
    protected $table = 'recruitment_activity_logs';

    protected $fillable = [
        'message',
        'type',
        'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public static function record(string $message, string $type = 'gray'): void
    {
        static::create([
            'message' => $message,
            'type' => $type,
            'logged_at' => now(),
        ]);

        $oldest = static::orderByDesc('logged_at')->skip(50)->first();
        if ($oldest) {
            static::where('logged_at', '<=', $oldest->logged_at)->delete();
        }
    }
}
