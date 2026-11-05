<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AfterSaleLog extends Model
{
    use HasFactory;

    public const ACTION_CREATE = 'create';
    public const ACTION_ASSIGN = 'assign';
    public const ACTION_PROGRESS = 'progress';
    public const ACTION_RESOLVE = 'resolve';
    public const ACTION_CLOSE = 'close';
    public const ACTION_REOPEN = 'reopen';
    public const ACTION_NOTE = 'note';

    protected $fillable = ['ticket_id', 'operator_id', 'action', 'content'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(AfterSaleTicket::class, 'ticket_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            self::ACTION_CREATE => '创建',
            self::ACTION_ASSIGN => '指派',
            self::ACTION_PROGRESS => '处理',
            self::ACTION_RESOLVE => '解决',
            self::ACTION_CLOSE => '关闭',
            self::ACTION_REOPEN => '重开',
            self::ACTION_NOTE => '备注',
            default => $action,
        };
    }
}
