@extends('layouts.app')

@section('title', '退货单 ' . $return->return_no)
@section('desc', '销售退货详情 · 入库回补 · 退款记账')

@section('actions')
    <a class="btn" href="{{ route('sales.returns') }}">返回列表</a>
    @if ($return->order)
        <a class="btn" href="{{ route('sales.orders.show', $return->order) }}">原销售单 {{ $return->order->so_no }}</a>
    @endif
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">退货信息</h2>
            <span class="badge badge-{{ $return->statusTone($return->status) }}">{{ $return->statusLabel($return->status) }}</span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field"><span class="field-label">退货单号</span><div class="strong mono">{{ $return->return_no }}</div></div>
                <div class="field"><span class="field-label">客户</span><div>{{ $return->customer?->name ?? '散客' }}</div></div>
                <div class="field"><span class="field-label">退货日期</span><div class="muted">{{ $return->return_date?->format('Y-m-d') ?? '—' }}</div></div>
                <div class="field"><span class="field-label">原因</span><div class="muted">{{ $return->reason ?: '—' }}</div></div>
                <div class="field"><span class="field-label">退货金额</span><div class="strong">¥{{ number_format($return->total_amount, 2) }}</div></div>
                <div class="field"><span class="field-label">已退金额</span><div class="strong {{ $return->refund_amount > 0 ? 'down' : 'muted' }}">¥{{ number_format($return->refund_amount, 2) }}</div></div>
                <div class="field"><span class="field-label">入库时间</span><div class="muted">{{ $return->stocked_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
                <div class="field"><span class="field-label">退款时间</span><div class="muted">{{ $return->refunded_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
                <div class="field"><span class="field-label">创建人</span><div class="muted">{{ $return->creator?->name ?? '—' }}</div></div>
                <div class="field"><span class="field-label">备注</span><div class="muted">{{ $return->remark ?: '—' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">退货明细</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>SKU</th><th>商品</th><th class="num">数量</th><th class="num">单价</th><th class="num">小计</th></tr></thead>
                    <tbody>
                    @forelse ($return->items as $it)
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
                    <tr><td colspan="4" class="num strong">合计</td><td class="num strong down">¥{{ number_format($return->total_amount, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if ($return->status === \App\Models\SaleReturn::STATUS_PENDING)
        <div class="card">
            <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
                <form method="post" action="{{ route('sales.returns.stock', $return) }}" onsubmit="return confirm('确认退货入库？将按明细回补库存')">@csrf<button class="btn btn-primary" type="submit">退货入库（回补库存）</button></form>
                <form method="post" action="{{ route('sales.returns.cancel', $return) }}" onsubmit="return confirm('确认取消该退货单？')">@csrf<button class="btn btn-danger" type="submit">取消退货单</button></form>
            </div>
        </div>
    @elseif ($return->status === \App\Models\SaleReturn::STATUS_STOCKED)
        <div class="card">
            <div class="card-head"><h2 class="card-title">办理退款</h2></div>
            <div class="card-body">
                <form method="post" action="{{ route('sales.returns.refund', $return) }}" class="inline-form" onsubmit="return confirm('确认退款？资金流水将记入所选账户')">
                    @csrf
                    <select name="account_id" style="width:180px" required>
                        <option value="">选择退款账户</option>
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}">{{ $a->name }}（余额 ¥{{ number_format($a->balance, 2) }}）</option>
                        @endforeach
                    </select>
                    <input type="number" name="amount" step="0.01" min="0.01" max="{{ $return->total_amount }}" value="{{ $return->total_amount }}" style="width:140px" required>
                    <button class="btn btn-primary" type="submit">确认退款</button>
                    <span class="muted" style="font-size:12px">退款金额默认等于退货金额，可改小（部分退）</span>
                </form>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body">
                <div class="alert alert-ok"><span>✓</span><span>该退货单已{{ $return->status === \App\Models\SaleReturn::STATUS_REFUNDED ? '完成退款，闭环' : '取消' }}</span></div>
            </div>
        </div>
    @endif
@endsection
