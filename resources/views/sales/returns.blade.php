@extends('layouts.app')

@section('title', '销售退货')
@section('desc', '退货单 · 质检入库回补库存 · 退款冲减应收')

@section('actions')
    <a class="btn btn-primary" href="{{ route('sales.returns.select') }}">＋ 新建退货单</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">退货单列表</h2>
            <form method="get" action="{{ route('sales.returns') }}" class="inline-form">
                <select name="status" style="width:120px">
                    <option value="">全部状态</option>
                    @foreach (['pending' => '待入库', 'stocked' => '已入库', 'refunded' => '已退款', 'canceled' => '已取消'] as $k => $v)
                        <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $v }}</option>
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
                        <th>单号</th><th>原销售单</th><th>客户</th><th>状态</th>
                        <th class="num">退货金额</th><th class="num">已退金额</th><th>原因</th><th class="nowrap">退货日期</th><th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($list as $r)
                        <tr>
                            <td class="mono strong">{{ $r->return_no }}</td>
                            <td class="mono"><a href="{{ route('sales.orders.show', $r->order) }}">{{ $r->order?->so_no }}</a></td>
                            <td>{{ $r->customer?->name ?? '散客' }}</td>
                            <td><span class="badge badge-{{ $r->statusTone($r->status) }}">{{ $r->statusLabel($r->status) }}</span></td>
                            <td class="num strong">¥{{ number_format($r->total_amount, 2) }}</td>
                            <td class="num muted">¥{{ number_format($r->refund_amount, 2) }}</td>
                            <td class="muted">{{ $r->reason ?: '—' }}</td>
                            <td class="nowrap mono">{{ $r->return_date?->format('Y-m-d') }}</td>
                            <td class="nowrap"><a class="btn btn-sm" href="{{ route('sales.returns.show', $r) }}">查看</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty"><div class="big">↩</div>暂无退货单，点击右上角新建</div></td></tr>
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
