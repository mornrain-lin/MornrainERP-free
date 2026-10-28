@extends('layouts.app')

@section('title', '销售订单 ' . $order->so_no)
@section('desc', 'B 端销售订单详情 · 状态流转 · 回款')

@section('actions')
    <a class="btn" href="{{ route('sales.orders') }}">返回列表</a>
    @if (in_array($order->status, [\App\Models\SaleOrder::STATUS_SHIPPED, \App\Models\SaleOrder::STATUS_COMPLETED], true))
        <a class="btn" href="{{ route('sales.returns.create', $order) }}">↩ 申请退货</a>
    @endif
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">订单信息</h2>
            <span class="badge badge-{{ $order->statusTone($order->status) }}">{{ $order->statusLabel($order->status) }}</span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field"><span class="field-label">单号</span><div class="strong mono">{{ $order->so_no }}</div></div>
                <div class="field"><span class="field-label">客户</span><div>{{ $order->customer?->name ?? '散客' }}</div></div>
                <div class="field"><span class="field-label">下单日期</span><div class="muted">{{ $order->order_date?->format('Y-m-d') ?? '—' }}</div></div>
                <div class="field"><span class="field-label">预计交货</span><div class="muted">{{ $order->expected_at?->format('Y-m-d') ?? '—' }}</div></div>
                <div class="field"><span class="field-label">发货时间</span><div class="muted">{{ $order->shipped_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
                <div class="field"><span class="field-label">创建人</span><div class="muted">{{ $order->creator?->name ?? '—' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">销售明细</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>SKU</th><th>商品</th><th class="num">数量</th><th class="num">单价</th><th class="num">小计</th></tr></thead>
                    <tbody>
                    @forelse ($order->items as $it)
                        <tr>
                            <td class="mono">{{ $it->sku }}</td>
                            <td>{{ $it->product_name }}</td>
                            <td class="num">{{ $it->quantity }}</td>
                            <td class="num">¥{{ number_format($it->unit_price, 2) }}</td>
                            <td class="num strong">¥{{ number_format($it->line_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">无明细</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr><td colspan="4" class="num strong">合计</td><td class="num strong up">¥{{ number_format($order->total_amount, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">回款</h2></div>
        <div class="card-body">
            <div class="form-grid" style="margin-bottom:14px">
                <div class="field"><span class="field-label">总额</span><div class="strong">¥{{ number_format($order->total_amount, 2) }}</div></div>
                <div class="field"><span class="field-label">已回款</span><div class="strong up">¥{{ number_format($order->paid_amount, 2) }}</div></div>
                <div class="field"><span class="field-label">未回款</span><div class="strong {{ $order->remaining > 0 ? 'down' : 'muted' }}">¥{{ number_format($order->remaining, 2) }}</div></div>
            </div>
            @if ($order->remaining > 0 && $order->status !== \App\Models\SaleOrder::STATUS_CANCELED)
                <form method="post" action="{{ route('sales.orders.pay', $order) }}" class="inline-form">
                    @csrf
                    <input type="number" name="amount" step="0.01" min="0.01" max="{{ $order->remaining }}" placeholder="本次回款金额" style="width:160px" required>
                    <button class="btn btn-primary btn-sm" type="submit">登记回款</button>
                    <span class="muted" style="font-size:12px">资金流水请到「财务管理」登记</span>
                </form>
            @else
                <div class="alert alert-ok"><span>✓</span><span>已结清</span></div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
            @if ($order->status === \App\Models\SaleOrder::STATUS_DRAFT)
                <form method="post" action="{{ route('sales.orders.confirm', $order) }}">@csrf<button class="btn btn-primary" type="submit">确认订单</button></form>
                <form method="post" action="{{ route('sales.orders.cancel', $order) }}" onsubmit="return confirm('确认取消？')">@csrf<button class="btn btn-danger" type="submit">取消订单</button></form>
            @elseif ($order->status === \App\Models\SaleOrder::STATUS_CONFIRMED)
                <form method="post" action="{{ route('sales.orders.ship', $order) }}" onsubmit="return confirm('发货将按明细扣减库存，确认？')">@csrf<button class="btn btn-primary" type="submit">发货（扣库存）</button></form>
                <form method="post" action="{{ route('sales.orders.cancel', $order) }}" onsubmit="return confirm('确认取消？')">@csrf<button class="btn btn-danger" type="submit">取消订单</button></form>
            @elseif ($order->status === \App\Models\SaleOrder::STATUS_SHIPPED)
                <form method="post" action="{{ route('sales.orders.complete', $order) }}">@csrf<button class="btn btn-primary" type="submit">完成订单</button></form>
            @else
                <span class="muted">该订单已终态（{{ $order->statusLabel($order->status) }}），无可执行操作</span>
            @endif
        </div>
    </div>
@endsection
