<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmAssetLog extends Model
{
    use HasFactory;

    public const ACTION_RECEIVE = 'receive';       // 领用
    public const ACTION_RETURN = 'return';         // 退库
    public const ACTION_REPAIR = 'repair';         // 送修
    public const ACTION_REPAIR_DONE = 'repair_done'; // 修复
    public const ACTION_RETIRE = 'retire';         // 报废

    protected $fillable = [
        'asset_id', 'action', 'operator_id', 'target_user_id', 'remark',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(AdmAsset::class, 'asset_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public static function actionLabels(): array
    {
        return [
            self::ACTION_RECEIVE => '领用',
            self::ACTION_RETURN => '退库',
            self::ACTION_REPAIR => '送修',
            self::ACTION_REPAIR_DONE => '修复',
            self::ACTION_RETIRE => '报废',
        ];
    }

    public static function actionLabel(string $action): string
    {
        return self::actionLabels()[$action] ?? $action;
    }

    public static function actionTone(string $action): string
    {
        return match ($action) {
            self::ACTION_RECEIVE => 'info',
            self::ACTION_RETURN => 'muted',
            self::ACTION_REPAIR => 'warn',
            self::ACTION_REPAIR_DONE => 'success',
            self::ACTION_RETIRE => 'danger',
            default => 'muted',
        };
    }
}
