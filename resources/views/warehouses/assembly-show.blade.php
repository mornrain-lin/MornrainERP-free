@extends('layouts.app')

@section('title', '组装 / 拆卸单详情')
@section('desc', '单号 ' . ($assembly->doc_no ?? ''))

@section('actions')
    <a class="btn" href="{{ route('warehouses.assemblies.index') }}">返回列表</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">单据信息</h2>
            <span class="badge badge-{{ $assembly->statusTone($assembly->status) }}">{{ $assembly->statusLabel($assembly->status) }}</span>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field"><span class="field-label">单号</span><div class="strong mono">{{ $assembly->doc_no }}</div></div>
                <div class="field"><span class="field-label">类型</span>
                    <div>
                        <span class="badge {{ $assembly->type === \App\Models\Assembly::TYPE_DISASSEMBLE ? 'badge-info' : 'badge-success' }}">
                            {{ \App\Models\Assembly::typeLabel($assembly->type) }}
                        </span>
                    </div>
                </div>
                <div class="field"><span class="field-label">成品 / 主件</span><div>{{ $assembly->product?->sku }} · {{ $assembly->product?->name ?? '—' }}</div></div>
                <div class="field"><span class="field-label">{{ $assembly->type === 'disassemble' ? '拆卸数量' : '组装数量' }}</span><div class="num strong">{{ $assembly->qty }}</div></div>
                <div class="field"><span class="field-label">发起人</span><div class="muted">{{ $assembly->creator?->name ?? '—' }}</div></div>
                <div class="field"><span class="field-label">备注</span><div class="muted">{{ $assembly->note ?: '—' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">{{ $assembly->type === 'disassemble' ? '产出子件明细' : '耗用子件明细' }}</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr><th>SKU</th><th>商品</th><th class="num">每件数量</th><th class="num">合计（×{{ $assembly->qty }}）</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($assembly->items ?? [] as $row)
                        <tr>
                            <td class="mono">{{ $row['sku'] ?? '—' }}</td>
                            <td>{{ $row['name'] ?? '—' }}</td>
                            <td class="num">{{ $row['qty'] ?? 0 }}</td>
                            <td class="num strong">{{ ($row['qty'] ?? 0) * $assembly->qty }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="empty">无明细</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($assembly->status === \App\Models\Assembly::STATUS_PENDING)
        <div class="card">
            <div class="card-body" style="display:flex;gap:10px">
                <form method="post" action="{{ route('warehouses.assemblies.complete', $assembly) }}">
                    @csrf
                    <button class="btn btn-primary" type="submit">执行{{ \App\Models\Assembly::typeLabel($assembly->type) }}</button>
                </form>
                <form method="post" action="{{ route('warehouses.assemblies.cancel', $assembly) }}"
                      onsubmit="return confirm('确认取消该单据？')">
                    @csrf
                    <button class="btn btn-danger" type="submit">取消</button>
                </form>
            </div>
        </div>
    @elseif ($assembly->status === \App\Models\Assembly::STATUS_DONE)
        <div class="alert alert-ok"><span>✓</span><span>{{ \App\Models\Assembly::typeLabel($assembly->type) }}已完成（{{ $assembly->done_at?->format('m-d H:i') }}），库存已自动过账</span></div>
    @endif
@endsection
