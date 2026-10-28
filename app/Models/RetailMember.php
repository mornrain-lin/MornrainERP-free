<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetailMember extends Model
{
    use HasFactory;

    public const LEVEL_NORMAL = 'normal';
    public const LEVEL_SILVER = 'silver';
    public const LEVEL_GOLD = 'gold';

    protected $fillable = ['card_no', 'name', 'phone', 'level', 'discount', 'points', 'balance', 'remark', 'is_active'];

    protected $casts = [
        'discount' => 'decimal:2',
        'points' => 'int',
        'balance' => 'decimal:2',
        'is_active' => 'bool',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(RetailSale::class, 'member_id');
    }

    public static function levelLabel(string $level): string
    {
        return match ($level) {
            self::LEVEL_SILVER => '银卡',
            self::LEVEL_GOLD => '金卡',
            default => '普通',
        };
    }

    /** 折扣描述，如 95 折 / 无折扣 */
    public function discountText(): string
    {
        return (float) $this->discount >= 1.0 ? '无折扣' : rtrim(rtrim(number_format((float) $this->discount * 10, 1), '0'), '.') . ' 折';
    }
}
