<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\Traits\BelongsToOrganization;
class FinanceFlow extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    public const DIRECTION_INCOME = 'income';
    public const DIRECTION_EXPENSE = 'expense';

    // 收入分类
    public const CAT_SALES = 'sales';           // 销售收入
    public const CAT_REFUND_IN = 'refund_in';   // 退款收回
    public const CAT_OTHER_IN = 'other_in';     // 其他收入
    public const CAT_INTERCOMPANY_IN = 'intercompany_in';   // 内部往来收入（合并时按登记抵消）
    // 支出分类
    public const CAT_PURCHASE = 'purchase';     // 采购支出
    public const CAT_SHIPPING = 'shipping';     // 物流费用
    public const CAT_AD = 'ad';                 // 广告费用
    public const CAT_COMMISSION = 'commission'; // 平台佣金
    public const CAT_FEE = 'fee';               // 支付手续费
    public const CAT_REFUND_OUT = 'refund_out'; // 退款支出
    public const CAT_PREPAY = 'prepay';         // 预付款 / 预收款
    public const CAT_OTHER_OUT = 'other_out';   // 其他支出
    public const CAT_INTERCOMPANY_OUT = 'intercompany_out'; // 内部往来支出（合并时按登记抵消）

    protected $fillable = [
        'flow_no', 'direction', 'category', 'amount', 'account_id',
        'occurred_on', 'related_type', 'related_id', 'remark', 'created_by',
        // 合并核算：显式指定组织、币别与内部往来登记
        // （organization_id 不指定时由 BelongsToOrganization 回填当前组织）
        'organization_id', 'intercompany_id', 'currency',
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

    public function intercompany(): BelongsTo
    {
        return $this->belongsTo(OrgIntercompanyTransaction::class, 'intercompany_id');
    }

    public function isIntercompany(): bool
    {
        return $this->intercompany_id !== null;
    }

    public static function categories(string $direction): array
    {
        return $direction === self::DIRECTION_INCOME
            ? [
                self::CAT_SALES => '销售收入',
                self::CAT_REFUND_IN => '退款收回',
                self::CAT_OTHER_IN => '其他收入',
                self::CAT_INTERCOMPANY_IN => '内部往来收入',
            ]
            : [
                self::CAT_PURCHASE => '采购支出',
                self::CAT_SHIPPING => '物流费用',
                self::CAT_AD => '广告费用',
                self::CAT_COMMISSION => '平台佣金',
                self::CAT_FEE => '支付手续费',
                self::CAT_REFUND_OUT => '退款支出',
                self::CAT_OTHER_OUT => '其他支出',
                self::CAT_INTERCOMPANY_OUT => '内部往来支出',
            ];
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
