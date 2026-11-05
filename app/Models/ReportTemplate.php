<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'description', 'data_source', 'columns', 'filters', 'is_active', 'created_by'];

    protected $casts = [
        'columns' => 'array',
        'filters' => 'array',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return $this->is_active ? '启用' : '停用';
    }

    public function statusTone(): string
    {
        return $this->is_active ? 'success' : 'muted';
    }
}
