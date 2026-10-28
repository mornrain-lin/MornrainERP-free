@extends('layouts.app')

@section('title', '报价单 ' . $quote->quote_no)
@section('desc', '报价详情 · 可更新状态或一键转销售订单')

@section('actions')
    <a class="btn" href="{{ route('sales.quotes') }}">返回列表</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">报价信息</h2>
            <span class="badge badge-{{ $quote->statusTone($quote->status) }}">{{ $quote->statusLabel($quote->status) }}</span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field"><span class="field-label">单号</span><div class="strong mono">{{ $quote->quote_no }}</div></div>
                <div class="field"><span class="field-label">客户</span><div>{{ $quote->customer?->name ?? '散客' }}</div></div>
                <div class="field"><span class="field-label">有效期至</span><div class="muted">{{ $quote->valid_until?->format('Y-m-d') ?? '—' }}</div></div>
                <div class="field"><span class="field-label">创建人</span><div class="muted">{{ $quote->creator?->name ?? '—' }}</div></div>
                <div class="field"><span class="field-label">备注</span><div class="muted">{{ $quote->remark ?: '—' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">报价明细</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>SKU</th><th>商品</th><th class="num">数量</th><th class="num">单价</th><th class="num">小计</th></tr></thead>
                    <tbody>
                    @forelse ($quote->items ?? [] as $row)
                        <tr>
                            <td class="mono">{{ $row['sku'] ?? '—' }}</td>
                            <td>{{ $row['name'] ?? '—' }}</td>
                            <td class="num">{{ $row['qty'] ?? 0 }}</td>
                            <td class="num">¥{{ number_format($row['unit_price'] ?? 0, 2) }}</td>
                            <td class="num strong">¥{{ number_format(($row['qty'] ?? 0) * ($row['unit_price'] ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">无明细</td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr><td colspan="4" class="num strong">合计</td><td class="num strong up">¥{{ number_format($quote->total_amount, 2) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            @if ($quote->status !== \App\Models\SaleQuote::STATUS_CONVERTED)
                <form method="post" action="{{ route('sales.quotes.convert', $quote) }}" onsubmit="return confirm('将基于本报价生成销售订单（草稿）？')">
                    @csrf
                    <button class="btn btn-primary" type="submit">转销售订单</button>
                </form>
                <form method="post" action="{{ route('sales.quotes.status', $quote) }}" class="inline-form">
                    @csrf
                    <select name="status" style="width:120px">
                        <option value="draft" {{ $quote->status === 'draft' ? 'selected' : '' }}>草稿</option>
                        <option value="sent" {{ $quote->status === 'sent' ? 'selected' : '' }}>已发送</option>
                        <option value="accepted" {{ $quote->status === 'accepted' ? 'selected' : '' }}>已接受</option>
                        <option value="rejected" {{ $quote->status === 'rejected' ? 'selected' : '' }}>已拒绝</option>
                        <option value="expired" {{ $quote->status === 'expired' ? 'selected' : '' }}>已过期</option>
                    </select>
                    <button class="btn btn-sm" type="submit">更新状态</button>
                </form>
            @else
                <div class="alert alert-ok"><span>✓</span><span>该报价单已转为销售订单</span></div>
            @endif
        </div>
    </div>
@endsection
