<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OaAnnouncement extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'title', 'content', 'status', 'is_pinned', 'published_at', 'created_by',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PUBLISHED => '已发布',
            default => '草稿',
        };
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_PUBLISHED => 'success',
            default => 'muted',
        };
    }
}
