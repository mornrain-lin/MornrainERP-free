<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceAccount extends Model
{
    use HasFactory;

    public const TYPE_CASH = 'cash';
    public const TYPE_BANK = 'bank';
    public const TYPE_ALIPAY = 'alipay';
    public const TYPE_WECHAT = 'wechat';
    public const TYPE_OTHER = 'other';

    protected $fillable = ['name', 'type', 'balance', 'is_active', 'note'];

    protected $casts = [
        'is_active' => 'bool',
        'balance' => 'decimal:2',
    ];

    public function flows(): HasMany
    {
        return $this->hasMany(FinanceFlow::class, 'account_id');
    }

    public function prepayments(): HasMany
    {
        return $this->hasMany(FinancePrepayment::class, 'account_id');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_CASH => '现金',
            self::TYPE_BANK => '银行',
            self::TYPE_ALIPAY => '支付宝',
            self::TYPE_WECHAT => '微信',
            default => '其他',
        };
    }
}
