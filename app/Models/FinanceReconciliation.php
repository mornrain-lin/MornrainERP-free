<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Traits\BelongsToOrganization;
class FinanceReconciliation extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    public const STATUS_BALANCED = 'balanced';
    public const STATUS_DIFF = 'diff';

    protected $fillable = [
        'account_id', 'period_start', 'period_end', 'system_balance',
        'actual_balance', 'difference', 'status', 'note', 'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'system_balance' => 'decimal:2',
        'actual_balance' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function statusLabel(string $status): string
    {
        return $status === self::STATUS_DIFF ? '有差异' : '已平账';
    }

    public static function statusTone(string $status): string
    {
        return $status === self::STATUS_DIFF ? 'danger' : 'success';
    }
}
