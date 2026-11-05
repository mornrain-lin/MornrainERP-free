<?php

namespace App\Models;

use App\Models\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 采购请购单
 *
 * 状态机：draft 草稿 → pending 审批中 → approved 已通过 → converted 已转采购单
 *                                    ↘ rejected 已驳回（可修改后重新提交）
 *         draft / pending 可取消（canceled）
 */
class PurchaseRequisition extends Model
{
    use HasFactory;
    use BelongsToOrganization;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_CONVERTED = 'converted';

    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'requisition_no', 'department', 'applicant_id', 'expected_date', 'priority',
        'status', 'remark', 'workflow_instance_id', 'approved_by', 'approved_at',
        'approve_remark', 'purchase_order_id', 'converted_at', 'created_by',
    ];

    protected $casts = [
        'expected_date' => 'date',
        'approved_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionItem::class, 'requisition_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => '草稿',
            self::STATUS_PENDING => '审批中',
            self::STATUS_APPROVED => '已通过',
            self::STATUS_REJECTED => '已驳回',
            self::STATUS_CANCELED => '已撤回',
            self::STATUS_CONVERTED => '已转采购单',
            default => $status,
        };
    }

    /** 全部状态 → 标签（列表筛选用） */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => '草稿',
            self::STATUS_PENDING => '审批中',
            self::STATUS_APPROVED => '已通过',
            self::STATUS_REJECTED => '已驳回',
            self::STATUS_CANCELED => '已撤回',
            self::STATUS_CONVERTED => '已转采购单',
        ];
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'muted',
            self::STATUS_PENDING => 'warn',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELED => 'muted',
            self::STATUS_CONVERTED => 'info',
            default => 'muted',
        };
    }

    public static function priorityLabel(string $priority): string
    {
        return match ($priority) {
            self::PRIORITY_URGENT => '加急',
            default => '普通',
        };
    }

    /** 预估总额 = Σ 数量 × 预估单价（依赖已加载的 items 关系） */
    public function estAmount(): float
    {
        return (float) $this->items->sum(fn ($i) => (int) $i->quantity * (float) $i->unit_cost);
    }

    /** 请购总数量（依赖已加载的 items 关系） */
    public function totalQty(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /** 生成请购单号 PR+Ymd-XXXX */
    public static function nextNo(): string
    {
        return 'PR' . now()->format('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4));
    }
}
