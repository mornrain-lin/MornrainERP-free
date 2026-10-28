<?php

namespace App\Services\Purchase;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceLog;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 请购申请服务（采购闭环第一环）
 *
 *  - create / update：草稿维护（draft / rejected 可改）
 *  - submit：发起电子审批（WorkflowService，key=purchase.requisition），单子进入 pending
 *  - syncFromWorkflow：流程引擎回调，审批结论（通过 / 驳回 / 撤回）写回请购单
 *  - cancel：撤回审批中的请购单（联动取消流程实例）
 *  - convert：审批通过后一键转采购单（草稿态），明细带预估单价
 *
 * 状态流转只允许：
 *   draft/rejected → pending → approved → converted
 *   draft/pending  → canceled
 */
class PurchaseRequisitionService
{
    public function __construct(private WorkflowService $workflows) {}

    public function create(User $user, array $data): PurchaseRequisition
    {
        return DB::transaction(function () use ($user, $data) {
            $req = PurchaseRequisition::create([
                'requisition_no' => PurchaseRequisition::nextNo(),
                'department' => $data['department'],
                'applicant_id' => $user->id,
                'expected_date' => $data['expected_date'] ?? null,
                'priority' => $data['priority'] ?? PurchaseRequisition::PRIORITY_NORMAL,
                'status' => PurchaseRequisition::STATUS_DRAFT,
                'remark' => $data['remark'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->syncItems($req, $data['items']);

            return $req;
        });
    }

    public function update(PurchaseRequisition $req, array $data): PurchaseRequisition
    {
        if (! in_array($req->status, [PurchaseRequisition::STATUS_DRAFT, PurchaseRequisition::STATUS_REJECTED], true)) {
            throw new RuntimeException('仅「草稿 / 已驳回」状态可编辑');
        }

        return DB::transaction(function () use ($req, $data) {
            $req->update([
                'department' => $data['department'],
                'expected_date' => $data['expected_date'] ?? null,
                'priority' => $data['priority'] ?? PurchaseRequisition::PRIORITY_NORMAL,
                'remark' => $data['remark'] ?? null,
            ]);

            $this->syncItems($req, $data['items']);

            return $req;
        });
    }

    /** 提交审批：草稿 / 已驳回 → 审批中，并发起流程实例 */
    public function submit(PurchaseRequisition $req): PurchaseRequisition
    {
        if (! in_array($req->status, [PurchaseRequisition::STATUS_DRAFT, PurchaseRequisition::STATUS_REJECTED], true)) {
            throw new RuntimeException('仅「草稿 / 已驳回」状态可提交审批');
        }
        if ($req->items()->count() === 0) {
            throw new RuntimeException('请购明细不能为空，请先添加至少一个 SKU');
        }

        $applicant = $req->applicant ?: new User(['id' => $req->applicant_id, 'name' => '申请人']);

        $req->loadMissing('items');
        $inst = $this->workflows->submit($applicant, 'purchase.requisition', [
            'business_type' => 'purchase_requisition',
            'business_id' => $req->id,
            'requisition_no' => $req->requisition_no,
            'department' => $req->department,
            'priority' => PurchaseRequisition::priorityLabel($req->priority),
            'expected_date' => $req->expected_date?->toDateString(),
            'items' => $req->items->map(fn ($i) => "{$i->sku} × {$i->quantity}")->implode('；'),
            'est_amount' => number_format($req->estAmount(), 2, '.', ''),
            '_title' => $req->requisition_no,
        ]);

        $req->update([
            'status' => PurchaseRequisition::STATUS_PENDING,
            'workflow_instance_id' => $inst->id,
            'approve_remark' => null,
        ]);
        // 同步关系缓存，避免同一请求内再次拿到旧实例（如提交后立即撤回）
        $req->setRelation('workflowInstance', $inst);

        return $req;
    }

    /** 撤回：审批中 → 已撤回，联动取消流程实例 */
    public function cancel(PurchaseRequisition $req): PurchaseRequisition
    {
        if ($req->status !== PurchaseRequisition::STATUS_PENDING) {
            throw new RuntimeException('仅「审批中」状态可撤回');
        }

        // 现查实例，防止关系缓存里是上一个流程实例
        $inst = $req->workflowInstance()->first();
        if ($inst && $inst->status === WorkflowInstance::STATUS_PENDING) {
            $this->workflows->cancel($inst, $req->applicant);
            $req->setRelation('workflowInstance', $inst);
        }

        $req->update(['status' => PurchaseRequisition::STATUS_CANCELED]);

        return $req;
    }

    /**
     * 流程引擎回调：把审批结论写回请购单（数据驱动，由 WorkflowService 在
     * approve / reject / cancel 后调用；实例 data 带 business_type/business_id）。
     * 已转采购单的单据不再回写，保证闭环后状态稳定。
     */
    public function syncFromWorkflow(WorkflowInstance $inst, PurchaseRequisition $req): PurchaseRequisition
    {
        if ($req->status === PurchaseRequisition::STATUS_CONVERTED) {
            return $req;
        }

        $data = match ($inst->status) {
            WorkflowInstance::STATUS_APPROVED => [
                'status' => PurchaseRequisition::STATUS_APPROVED,
                'approved_by' => $this->lastActor($inst, WorkflowInstanceLog::ACTION_APPROVE),
                'approved_at' => $inst->approved_at ?? now(),
                'approve_remark' => $this->lastComment($inst, WorkflowInstanceLog::ACTION_APPROVE),
            ],
            WorkflowInstance::STATUS_REJECTED => [
                'status' => PurchaseRequisition::STATUS_REJECTED,
                'approve_remark' => '驳回意见：' . ($this->lastComment($inst, WorkflowInstanceLog::ACTION_REJECT) ?: '无'),
            ],
            WorkflowInstance::STATUS_CANCELED => [
                'status' => PurchaseRequisition::STATUS_CANCELED,
            ],
            default => [],
        };

        if ($data && $req->status !== $data['status']) {
            $req->update($data);
        }

        return $req;
    }

    /** 转采购单：已通过 → 已转采购单，生成草稿态采购单并带入明细 */
    public function convert(PurchaseRequisition $req, int $supplierId): PurchaseOrder
    {
        if ($req->status !== PurchaseRequisition::STATUS_APPROVED) {
            throw new RuntimeException('仅审批通过的请购单可转采购单');
        }

        return DB::transaction(function () use ($req, $supplierId) {
            $po = PurchaseOrder::create([
                'po_no' => PurchaseOrder::nextNo(),
                'supplier_id' => $supplierId,
                'status' => \App\Enums\POStatus::Draft->value,
                'order_date' => now()->toDateString(),
                'expected_at' => $req->expected_date,
                'created_by' => auth()->id(),
                'remark' => "由请购单 {$req->requisition_no} 转采购单",
            ]);

            foreach ($req->items as $it) {
                $po->items()->create([
                    'product_id' => $it->product_id,
                    'sku' => $it->sku,
                    'product_name' => $it->product_name,
                    'quantity' => $it->quantity,
                    'unit_cost' => $it->unit_cost,
                    'received_qty' => 0,
                ]);
            }

            $po->recalcTotal();

            $req->update([
                'status' => PurchaseRequisition::STATUS_CONVERTED,
                'purchase_order_id' => $po->id,
                'converted_at' => now(),
            ]);

            return $po;
        });
    }

    /** 重建明细（编辑即全量重建） */
    private function syncItems(PurchaseRequisition $req, array $items): void
    {
        $req->items()->delete();

        foreach ($items as $it) {
            $product = Product::where('sku', $it['sku'])->first();
            $req->items()->create([
                'product_id' => $product?->id,
                'sku' => $it['sku'],
                'product_name' => ($it['product_name'] ?? '') ?: ($product?->name ?? $it['sku']),
                'quantity' => (int) $it['quantity'],
                'unit_cost' => $it['unit_cost'] ?? $product?->cost_price ?? 0,
            ]);
        }
    }

    private function lastActor(WorkflowInstance $inst, string $action): ?int
    {
        return $inst->logs()->where('action', $action)->orderByDesc('id')->first()?->actor_id;
    }

    private function lastComment(WorkflowInstance $inst, string $action): ?string
    {
        return $inst->logs()->where('action', $action)->orderByDesc('id')->first()?->comment;
    }
}
