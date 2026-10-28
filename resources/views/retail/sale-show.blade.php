@extends('layouts.app')

@section('title', '零售单 ' . $sale->sale_no)
@section('desc', '零售小票 · 商品明细 · 退货')

@section('actions')
    <a class="btn" href="{{ route('retail.sales') }}">返回列表</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">小票信息</h2>
            <span class="badge badge-{{ $sale->statusTone($sale->status) }}">{{ $sale->statusLabel($sale->status) }}</span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field"><span class="field-label">单号</span><div class="strong mono">{{ $sale->sale_no }}</div></div>
                <div class="field"><span class="field-label">会员</span><div>{{ $sale->member?->name ?? '散客' }}</div></div>
                <div class="field"><span class="field-label">支付方式</span><div>{{ \App\Models\RetailSale::paymentLabel($sale->payment_method) }}</div></div>
                <div class="field"><span class="field-label">收银员</span><div class="muted">{{ $sale->cashier?->name ?? '—' }}</div></div>
                <div class="field"><span class="field-label">成交时间</span><div class="muted">{{ $sale->sold_at?->format('Y-m-d H:i') ?? '—' }}</div></div>
                <div class="field"><span class="field-label">获得积分</span><div class="muted">{{ $sale->points_earned }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">商品明细</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>SKU</th><th>商品</th><th class="num">数量</th><th class="num">单价</th><th class="num">小计</th></tr></thead>
                    <tbody>
                    @forelse ($sale->items as $it)
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
                    <tr><td colspan="4" class="num strong">实收</td><td class="num strong up">¥{{ number_format($sale->total, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if ($sale->returns->isNotEmpty())
        <div class="alert alert-err">
            <span>↩</span>
            <span>该单已退货：{{ $sale->returns->first()->return_no }}（退回 ¥{{ number_format($sale->returns->first()->total, 2) }}）</span>
        </div>
    @endif

    @if ($sale->status === \App\Models\RetailSale::STATUS_COMPLETED)
        <div class="card">
            <div class="card-body">
                <form method="post" action="{{ route('retail.sales.refund', $sale) }}" class="inline-form" style="flex-wrap:wrap;gap:10px"
                      onsubmit="return confirm('整单退货将回补库存并退款（储值支付退回余额），确认？')">
                    @csrf
                    <input type="text" name="reason" placeholder="退货原因" style="width:220px">
                    <button class="btn btn-danger" type="submit">整单退货</button>
                    <span class="muted" style="font-size:12px">当前为整单退；库存回补、{{ \App\Models\RetailSale::paymentLabel($sale->payment_method) }}原路退款</span>
                </form>
            </div>
        </div>
    @endif
@endsection
