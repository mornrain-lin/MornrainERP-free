@extends('layouts.app')

@section('title', '零售单')
@section('desc', '门店零售流水 · 开单即扣库存')

@section('actions')
    <a class="btn btn-primary" href="{{ route('retail.pos') }}">🛒 门店开单</a>
    <a class="btn" href="{{ route('retail.returns') }}">退货记录</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">零售单列表</h2>
            <form method="get" action="{{ route('retail.sales') }}" class="inline-form">
                <select name="status" style="width:120px">
                    <option value="">全部状态</option>
                    @foreach (['completed' => '已完成', 'refunded' => '已退货', 'canceled' => '已取消'] as $k => $v)
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
                    <tr><th>单号</th><th>会员</th><th>状态</th><th class="num">数量</th><th class="num">小计</th><th class="num">优惠</th><th class="num">实收</th><th>支付</th><th class="nowrap">时间</th><th>操作</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($list as $s)
                        <tr>
                            <td class="mono strong">{{ $s->sale_no }}</td>
                            <td>{{ $s->member?->name ?? '散客' }}</td>
                            <td><span class="badge badge-{{ $s->statusTone($s->status) }}">{{ $s->statusLabel($s->status) }}</span></td>
                            <td class="num">{{ (int) $s->items_sum_quantity }}</td>
                            <td class="num muted">¥{{ number_format($s->subtotal, 2) }}</td>
                            <td class="num down">−¥{{ number_format($s->discount, 2) }}</td>
                            <td class="num strong up">¥{{ number_format($s->total, 2) }}</td>
                            <td class="muted">{{ \App\Models\RetailSale::paymentLabel($s->payment_method) }}</td>
                            <td class="nowrap mono">{{ $s->sold_at?->format('m-d H:i') }}</td>
                            <td class="nowrap"><a class="btn btn-sm" href="{{ route('retail.sales.show', $s) }}">查看</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="empty"><div class="big">🧾</div>暂无零售单，去<a href="{{ route('retail.pos') }}">门店开单</a></div></td></tr>
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
