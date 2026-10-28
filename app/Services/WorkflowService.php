<?php

namespace App\Services;

use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceLog;
use App\Services\Purchase\PurchaseRequisitionService;

/**
 * 电子审批流引擎
 *
 * 流程模板定义集中在 config/workflows.php，本服务负责：
 *  - 读取模板（definition）
 *  - 发起（submit）：落库 workflow_instances，写入第一条「发起」日志
 *  - 审批（approve）：按 nodes 顺序逐节点推进，末节点通过即整单通过
 *  - 驳回（reject）/ 撤回（cancel）
 * 业务代码只需调用本服务，不自行写节点流转逻辑。
 */
class WorkflowService
{
    /** 按 key 取流程模板定义（附带 _key / _category） */
    public static function definition(string $key): ?array
    {
        foreach (config('workflows', []) as $category => $cat) {
            if (isset($cat['items'][$key])) {
                return $cat['items'][$key] + ['_key' => $key, '_category' => $cat['label'] ?? $category];
            }
        }

        return null;
    }

    /** 全部流程模板（按分类） */
    public static function allDefinitions(): array
    {
        return config('workflows', []);
    }

    /** 流程总数（用于目录页统计） */
    public static function totalCount(): int
    {
        $n = 0;
        foreach (config('workflows', []) as $cat) {
            $n += count($cat['items'] ?? []);
        }

        return $n;
    }

    public function submit(User $user, string $key, array $data): WorkflowInstance
    {
        $def = self::definition($key);
        if (! $def) {
            abort(404, '流程模板不存在');
        }

        $title = ($def['name'] ?? $key) . ' · ' . ($data['_title'] ?? $user->name);

        /** @var WorkflowInstance $inst */
        $inst = WorkflowInstance::create([
            'workflow_key' => $key,
            'category' => $def['_category'] ?? '',
            'title' => $title,
            'applicant_id' => $user->id,
            'data' => $data,
            'status' => WorkflowInstance::STATUS_PENDING,
            'current_node' => 0,
            'node_count' => count($def['nodes'] ?? []),
        ]);

        $inst->logs()->create([
            'node' => 0,
            'action' => WorkflowInstanceLog::ACTION_SUBMIT,
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'comment' => '发起申请',
        ]);

        return $inst;
    }

    public function approve(WorkflowInstance $inst, User $user, ?string $comment): WorkflowInstance
    {
        if ($inst->status !== WorkflowInstance::STATUS_PENDING) {
            return $inst;
        }

        $node = $inst->current_node;
        $inst->logs()->create([
            'node' => $node,
            'action' => WorkflowInstanceLog::ACTION_APPROVE,
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'comment' => $comment,
        ]);

        if ($node + 1 >= $inst->node_count) {
            $inst->status = WorkflowInstance::STATUS_APPROVED;
            $inst->current_node = $inst->node_count;
            $inst->approved_at = now();
        } else {
            $inst->current_node = $node + 1;
        }
        $inst->save();

        $this->syncBusiness($inst);

        return $inst;
    }

    public function reject(WorkflowInstance $inst, User $user, ?string $comment): WorkflowInstance
    {
        if ($inst->status !== WorkflowInstance::STATUS_PENDING) {
            return $inst;
        }

        $inst->logs()->create([
            'node' => $inst->current_node,
            'action' => WorkflowInstanceLog::ACTION_REJECT,
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'comment' => $comment,
        ]);
        $inst->status = WorkflowInstance::STATUS_REJECTED;
        $inst->save();

        $this->syncBusiness($inst);

        return $inst;
    }

    public function cancel(WorkflowInstance $inst, User $user): WorkflowInstance
    {
        if ($inst->status !== WorkflowInstance::STATUS_PENDING) {
            return $inst;
        }

        $inst->logs()->create([
            'node' => $inst->current_node,
            'action' => WorkflowInstanceLog::ACTION_CANCEL,
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'comment' => '发起者撤回',
        ]);
        $inst->status = WorkflowInstance::STATUS_CANCELED;
        $inst->save();

        $this->syncBusiness($inst);

        return $inst;
    }

    /**
     * 审批结论回写业务单据（数据驱动，引擎不感知具体业务）：
     * 流程实例 data 里带 business_type / business_id 时，按类型分发到对应处理器。
     * 当前支持 purchase_requisition（请购申请）与 hr_leave（请假）；新业务接入只需在 data 里声明类型。
     */
    private function syncBusiness(WorkflowInstance $inst): void
    {
        $data = $inst->data ?? [];
        $type = $data['business_type'] ?? null;
        $id = $data['business_id'] ?? null;

        if ($type === 'purchase_requisition' && $id) {
            $req = PurchaseRequisition::find($id);
            if ($req) {
                app(PurchaseRequisitionService::class)->syncFromWorkflow($inst, $req);
            }

            return;
        }

    }
}
