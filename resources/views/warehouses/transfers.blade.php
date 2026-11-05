@extends('layouts.app')

@section('title', '仓库调拨')
@section('desc', '在多个仓库之间转移库存，总量不变、分仓此消彼长')

@section('actions')
    <a class="btn btn-primary" href="{{ route('warehouses.transfers.create') }}">＋ 新建调拨单</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">调拨单列表</h2>
            <span class="muted" style="font-size:12px">调出仓扣减、调入仓增加，执行后总量不变</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>单号</th><th>调出仓</th><th>调入仓</th><th class="num">明细</th>
                        <th>状态</th><th>发起人</th><th class="nowrap">创建时间</th><th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($list as $t)
                        <tr>
                            <td class="mono strong">{{ $t->transfer_no }}</td>
                            <td>{{ $t->fromWarehouse?->name ?? '—' }}</td>
                            <td>{{ $t->toWarehouse?->name ?? '—' }}</td>
                            <td class="num">{{ count($t->items ?? []) }}</td>
                            <td>
                                <span class="badge badge-{{ $t->statusTone($t->status) }}">{{ $t->statusLabel($t->status) }}</span>
                            </td>
                            <td class="muted">{{ $t->creator?->name ?? '—' }}</td>
                            <td class="nowrap mono">{{ $t->created_at->format('m-d H:i') }}</td>
                            <td class="nowrap">
                                <a class="btn btn-sm" href="{{ route('warehouses.transfers.show', $t) }}">查看</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty"><div class="big">⇄</div>还没有调拨单，点击右上角新建</div></td></tr>
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
