@extends('layouts.app')

@section('title', '请购申请')
@section('desc', '部门请购需求 → 审批流转 → 转采购单，衔接采购闭环')

@section('actions')
    <a class="btn btn-primary" href="{{ route('requisitions.create') }}">＋ 新建请购单</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">请购单列表</h2>
            <form method="get" action="{{ route('requisitions.index') }}" class="inline-form">
                <input type="text" name="q" value="{{ $q }}" placeholder="单号 / 部门 / 备注" style="width:180px">
                <select name="status" style="width:120px">
                    <option value="all">全部状态</option>
                    @foreach (\App\Models\PurchaseRequisition::statusLabels() as $k => $v)
                        <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm" type="submit">筛选</button>
            </form>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>单号</th><th>部门</th><th>申请人</th><th>优先级</th><th>状态</th>
                        <th class="num">明细</th><th class="num">总数量</th><th class="num">预估金额</th>
                        <th class="nowrap">期望到货</th><th class="nowrap">创建时间</th><th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($list as $r)
                        <tr>
                            <td class="mono strong">{{ $r->requisition_no }}</td>
                            <td>{{ $r->department }}</td>
                            <td>{{ $r->applicant?->name ?? '—' }}</td>
                            <td>
                                @if ($r->priority === \App\Models\PurchaseRequisition::PRIORITY_URGENT)
                                    <span class="badge badge-danger">加急</span>
                                @else
                                    <span class="muted">普通</span>
                                @endif
                            </td>
                            <td><span class="badge badge-{{ \App\Models\PurchaseRequisition::statusTone($r->status) }}">{{ \App\Models\PurchaseRequisition::statusLabel($r->status) }}</span></td>
                            <td class="num">{{ $r->items_count }}</td>
                            <td class="num">{{ $r->totalQty() }}</td>
                            <td class="num strong">¥{{ number_format($r->estAmount(), 2) }}</td>
                            <td class="nowrap mono">{{ $r->expected_date?->format('Y-m-d') ?? '—' }}</td>
                            <td class="nowrap mono">{{ $r->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="nowrap"><a class="btn btn-sm" href="{{ route('requisitions.show', $r) }}">查看</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><div class="empty"><div class="big">📨</div>暂无请购单，点击右上角新建</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($list->hasPages())
            <div class="pagination-wrap">{{ $list->links() }}</div>
        @endif
    </div>
@endsection
