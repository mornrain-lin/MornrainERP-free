@extends('layouts.app')

@section('title', '销售订单')
@section('desc', 'B 端销售开单 · 发货扣库存 · 回款跟踪')

@section('actions')
    <a class="btn btn-primary" href="{{ route('sales.orders.create') }}">＋ 新建销售订单</a>
    <a class="btn" href="{{ route('sales.quotes') }}">报价单</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">销售订单列表</h2>
            <form method="get" action="{{ route('sales.orders') }}" class="inline-form">
                <select name="status" style="width:120px">
                    <option value="">全部状态</option>
                    @foreach (['draft' => '草稿', 'confirmed' => '已确认', 'shipped' => '已发货', 'completed' => '已完成', 'canceled' => '已取消'] as $k => $v)
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
                        <th>单号</th><th>客户</th><th>状态</th><th class="num">数量</th><th class="num">总额</th>
                        <th class="num">已回款</th><th>回款</th><th class="nowrap">下单日</th><th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($list as $o)
                        <tr>
                            <td class="mono strong">{{ $o->so_no }}</td>
                            <td>{{ $o->customer?->name ?? '散客' }}</td>
                            <td><span class="badge badge-{{ $o->statusTone($o->status) }}">{{ $o->statusLabel($o->status) }}</span></td>
                            <td class="num">{{ (int) $o->items_sum_quantity }}</td>
                            <td class="num strong">¥{{ number_format($o->total_amount, 2) }}</td>
                            <td class="num muted">¥{{ number_format($o->paid_amount, 2) }}</td>
                            <td>
                                @if ($o->isSettled())
                                    <span class="badge badge-success">已结清</span>
                                @elseif ($o->paid_amount > 0)
                                    <span class="badge badge-warn">部分</span>
                                @else
                                    <span class="badge badge-muted">未回款</span>
                                @endif
                            </td>
                            <td class="nowrap mono">{{ $o->order_date?->format('Y-m-d') }}</td>
                            <td class="nowrap"><a class="btn btn-sm" href="{{ route('sales.orders.show', $o) }}">查看</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty"><div class="big">📝</div>暂无销售订单，点击右上角新建</div></td></tr>
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
