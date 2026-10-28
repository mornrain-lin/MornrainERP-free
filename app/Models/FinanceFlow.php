<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceFlow extends Model
{
    use HasFactory;

    public const DIRECTION_INCOME = 'income';
    public const DIRECTION_EXPENSE = 'expense';

    // 收入分类
    public const CAT_SALES = 'sales';           // 销售收入
    public const CAT_REFUND_IN = 'refund_in';   // 退款收回
    public const CAT_OTHER_IN = 'other_in';     // 其他收入
    // 支出分类
    public const CAT_PURCHASE = 'purchase';     // 采购支出
    public const CAT_SHIPPING = 'shipping';     // 物流费用
    public const CAT_AD = 'ad';                 // 广告费用
    public const CAT_COMMISSION = 'commission'; // 平台佣金
    public const CAT_FEE = 'fee';               // 支付手续费
    public const CAT_REFUND_OUT = 'refund_out'; // 退款支出
    public const CAT_PREPAY = 'prepay';         // 预付款 / 预收款
    public const CAT_OTHER_OUT = 'other_out';   // 其他支出

    protected $fillable = [
        'flow_no', 'direction', 'category', 'amount', 'account_id',
        'occurred_on', 'related_type', 'related_id', 'remark', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'occurred_on' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function categories(string $direction): array
    {
        return $direction === self::DIRECTION_INCOME
            ? [self::CAT_SALES => '销售收入', self::CAT_REFUND_IN => '退款收回', self::CAT_OTHER_IN => '其他收入']
            : [self::CAT_PURCHASE => '采购支出', self::CAT_SHIPPING => '物流费用', self::CAT_AD => '广告费用', self::CAT_COMMISSION => '平台佣金', self::CAT_FEE => '支付手续费', self::CAT_REFUND_OUT => '退款支出', self::CAT_OTHER_OUT => '其他支出'];
    }

    public static function categoryLabel(string $category): string
    {
        $all = self::categories(self::DIRECTION_INCOME) + self::categories(self::DIRECTION_EXPENSE);

        return $all[$category] ?? $category;
    }

    public static function directionLabel(string $direction): string
    {
        return $direction === self::DIRECTION_INCOME ? '收入' : '支出';
    }
}
