<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumPost extends Model
{
    use HasFactory;

    protected $fillable = ['thread_id', 'user_id', 'content'];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'thread_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
