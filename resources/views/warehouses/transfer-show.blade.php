@extends('layouts.app')

@section('title', '调拨单详情')
@section('desc', '调拨单 ' . ($transfer->transfer_no ?? ''))

@section('actions')
    <a class="btn" href="{{ route('warehouses.transfers.index') }}">返回列表</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">调拨信息</h2>
            <span class="badge badge-{{ $transfer->statusTone($transfer->status) }}">{{ $transfer->statusLabel($transfer->status) }}</span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field"><span class="field-label">单号</span><div class="strong mono">{{ $transfer->transfer_no }}</div></div>
                <div class="field"><span class="field-label">调出仓库</span><div>{{ $transfer->fromWarehouse?->name ?? '—' }}</div></div>
                <div class="field"><span class="field-label">调入仓库</span><div>{{ $transfer->toWarehouse?->name ?? '—' }}</div></div>
                <div class="field"><span class="field-label">发起人</span><div class="muted">{{ $transfer->creator?->name ?? '—' }}</div></div>
                <div class="field"><span class="field-label">创建时间</span><div class="muted nowrap">{{ $transfer->created_at->format('Y-m-d H:i') }}</div></div>
                <div class="field"><span class="field-label">备注</span><div class="muted">{{ $transfer->note ?: '—' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">调拨明细</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>SKU</th><th>商品</th><th class="num">数量</th></tr></thead>
                    <tbody>
                    @forelse ($transfer->items ?? [] as $row)
                        <tr>
                            <td class="mono">{{ $row['sku'] ?? '—' }}</td>
                            <td>{{ $row['name'] ?? '—' }}</td>
                            <td class="num strong">{{ $row['qty'] ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">无明细</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($transfer->status === \App\Models\StockTransfer::STATUS_PENDING)
        <div class="card">
            <div class="card-body" style="display:flex;gap:10px">
                <form method="post" action="{{ route('warehouses.transfers.complete', $transfer) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit">执行调拨（调出扣减 / 调入增加）</button>
                </form>
                <form method="post" action="{{ route('warehouses.transfers.cancel', $transfer) }}"
                      onsubmit="return confirm('确认取消该调拨单？')">
                    @csrf
                    <button class="btn btn-danger" type="submit">取消调拨单</button>
                </form>
            </div>
        </div>
    @elseif ($transfer->status === \App\Models\StockTransfer::STATUS_DONE)
        <div class="alert alert-ok"><span>✓</span><span>调拨已完成（{{ $transfer->done_at?->format('m-d H:i') }}），分仓库存已更新</span></div>
    @endif
@endsection
