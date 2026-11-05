@extends('layouts.app')

@section('title', '请购单 ' . $requisition->requisition_no)
@section('desc', '请购详情 · 审批流转 · 转采购单')

@section('actions')
    <a class="btn" href="{{ route('requisitions.index') }}">返回列表</a>
    @if ($requisition->purchaseOrder)
        <a class="btn" href="{{ route('purchases.show', $requisition->purchaseOrder) }}">采购单 {{ $requisition->purchaseOrder->po_no }}</a>
    @endif
    @if ($requisition->workflowInstance)
        <a class="btn" href="{{ route('workflows.show', $requisition->workflowInstance) }}">审批流程</a>
    @endif
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">请购信息</h2>
            <span class="badge badge-{{ \App\Models\PurchaseRequisition::statusTone($requisition->status) }}">
                {{ \App\Models\PurchaseRequisition::statusLabel($requisition->status) }}
            </span>
            @if ($requisition->priority === \App\Models\PurchaseRequisition::PRIORITY_URGENT)
                <span class="badge badge-danger">加急</span>
            @endif
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field"><span class="field-label">请购单号</span><div class="strong mono">{{ $requisition->requisition_no }}</div></div>
                <div class="field"><span class="field-label">申请部门</span><div>{{ $requisition->department }}</div></div>
                <div class="field"><span class="field-label">申请人</span><div class="muted">{{ $requisition->applicant?->name ?? '—' }}</div></div>
                <div class="field"><span class="field-label">期望到货</span><div class="muted">{{ $requisition->expected_date?->format('Y-m-d') ?? '—' }}</div></div>
                <div class="field"><span class="field-label">预估总额</span><div class="strong">¥{{ number_format($requisition->estAmount(), 2) }}</div></div>
                <div class="field"><span class="field-label">请购总量</span><div class="strong">{{ $requisition->totalQty() }}</div></div>
                <div class="field"><span class="field-label">审批人</span><div class="muted">{{ $requisition->approver?->name ?? '—' }}</div></div>
                <div class="field"><span class="field-label">审批时间</span><div class="muted">{{ $requisition->approved_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
                <div class="field"><span class="field-label">创建人</span><div class="muted">{{ $requisition->creator?->name ?? '—' }}</div></div>
                <div class="field"><span class="field-label">创建时间</span><div class="muted">{{ $requisition->created_at?->format('Y-m-d H:i') }}</div></div>
                <div class="field"><span class="field-label">备注</span><div class="muted">{{ $requisition->remark ?: '—' }}</div></div>
                @if ($requisition->approve_remark)
                    <div class="field"><span class="field-label">审批意见</span><div class="muted">{{ $requisition->approve_remark }}</div></div>
                @endif
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <div class="card-head"><h2 class="card-title">请购明细</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>SKU</th><th>商品</th><th class="num">数量</th><th class="num">预估单价</th><th class="num">小计</th></tr></thead>
                    <tbody>
                    @forelse ($requisition->items as $it)
                        <tr>
                            <td class="mono">{{ $it->sku }}</td>
                            <td>{{ $it->product_name }}</td>
                            <td class="num">{{ $it->quantity }}</td>
                            <td class="num">¥{{ number_format($it->unit_cost, 2) }}</td>
                            <td class="num strong">¥{{ number_format($it->line_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">无明细</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr><td colspan="4" class="num strong">合计</td><td class="num strong">¥{{ number_format($requisition->estAmount(), 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- 状态动作区 --}}
    @if (in_array($requisition->status, [\App\Models\PurchaseRequisition::STATUS_DRAFT, \App\Models\PurchaseRequisition::STATUS_REJECTED]))
        <div class="card" style="margin-top:18px">
            <div class="card-head"><h2 class="card-title">提交审批</h2></div>
            <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
                <form method="post" action="{{ route('requisitions.submit', $requisition) }}"
                      onsubmit="return confirm('提交后进入审批流程（直属主管 → 采购），确认提交？')">@csrf
                    <button class="btn btn-primary" type="submit">提交审批</button>
                </form>
                <a class="btn" href="{{ route('requisitions.edit', $requisition) }}">编辑</a>
                @if ($requisition->status === \App\Models\PurchaseRequisition::STATUS_DRAFT)
                    <form method="post" action="{{ route('requisitions.destroy', $requisition) }}"
                          onsubmit="return confirm('确认删除该请购单？')">@csrf @method('DELETE')
                        <button class="btn btn-danger" type="submit">删除</button>
                    </form>
                @endif
            </div>
        </div>
    @elseif ($requisition->status === \App\Models\PurchaseRequisition::STATUS_PENDING)
        <div class="card" style="margin-top:18px">
            <div class="card-head"><h2 class="card-title">审批中</h2></div>
            <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <span class="muted">请在「流程中心 → 我的待办」完成审批；审批结论会自动写回本单。</span>
                @if ($requisition->workflowInstance)
                    <a class="btn btn-sm" href="{{ route('workflows.show', $requisition->workflowInstance) }}">查看审批进度</a>
                @endif
                <form method="post" action="{{ route('requisitions.cancel', $requisition) }}"
                      onsubmit="return confirm('确认撤回审批？可修改后重新提交')">@csrf
                    <button class="btn btn-danger" type="submit">撤回</button>
                </form>
            </div>
        </div>
    @elseif ($requisition->status === \App\Models\PurchaseRequisition::STATUS_APPROVED)
        <div class="card" style="margin-top:18px">
            <div class="card-head"><h2 class="card-title">转采购单</h2></div>
            <div class="card-body">
                <form method="post" action="{{ route('requisitions.convert', $requisition) }}" class="inline-form"
                      onsubmit="return confirm('将按请购明细生成采购单（草稿态），确认转换？')">
                    @csrf
                    <select name="supplier_id" style="width:200px" required>
                        <option value="">选择供应商</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-primary" type="submit">转采购单</button>
                    @if ($suppliers->isEmpty())
                        <span class="hint" style="color:var(--danger)">还没有启用中的供应商，请先到「基础资料 → 供应商」新增</span>
                    @endif
                </form>
            </div>
        </div>
    @elseif ($requisition->status === \App\Models\PurchaseRequisition::STATUS_CONVERTED)
        <div class="card" style="margin-top:18px">
            <div class="card-body">
                <div class="alert alert-ok"><span>✓</span>
                    <span>已转采购单
                        @if ($requisition->purchaseOrder)
                            <a href="{{ route('purchases.show', $requisition->purchaseOrder) }}">{{ $requisition->purchaseOrder->po_no }}</a>
                        @endif
                        （{{ $requisition->converted_at?->format('Y-m-d H:i') }}），后续请到采购模块完成下单与收货
                    </span>
                </div>
            </div>
        </div>
    @else
        <div class="card" style="margin-top:18px">
            <div class="card-body">
                <div class="alert alert-{{ $requisition->status === \App\Models\PurchaseRequisition::STATUS_REJECTED ? 'err' : 'ok' }}">
                    <span>{{ $requisition->status === \App\Models\PurchaseRequisition::STATUS_REJECTED ? '!' : '✓' }}</span>
                    <span>该请购单已{{ $requisition->status === \App\Models\PurchaseRequisition::STATUS_REJECTED ? '被驳回，可修改后重新提交' : '撤回' }}</span>
                </div>
            </div>
        </div>
    @endif
@endsection
