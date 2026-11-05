@extends('layouts.app')

@section('title', '组装拆卸')
@section('desc', '将多个子件组装成成品，或将成品拆卸回子件，自动过账库存')

@section('actions')
    <a class="btn btn-primary" href="{{ route('warehouses.assemblies.create', ['type' => 'assemble']) }}">＋ 组装单</a>
    <a class="btn" href="{{ route('warehouses.assemblies.create', ['type' => 'disassemble']) }}">＋ 拆卸单</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">组装 / 拆卸单列表</h2>
            <span class="muted" style="font-size:12px">成品与子件均记于默认仓</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>单号</th><th>类型</th><th>成品 / 主件</th><th class="num">数量</th>
                        <th>状态</th><th>发起人</th><th class="nowrap">创建时间</th><th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($list as $a)
                        <tr>
                            <td class="mono strong">{{ $a->doc_no }}</td>
                            <td>
                                <span class="badge {{ $a->type === \App\Models\Assembly::TYPE_DISASSEMBLE ? 'badge-info' : 'badge-success' }}">
                                    {{ \App\Models\Assembly::typeLabel($a->type) }}
                                </span>
                            </td>
                            <td>{{ $a->product?->sku }} · {{ $a->product?->name ?? '—' }}</td>
                            <td class="num">{{ $a->qty }}</td>
                            <td><span class="badge badge-{{ $a->statusTone($a->status) }}">{{ $a->statusLabel($a->status) }}</span></td>
                            <td class="muted">{{ $a->creator?->name ?? '—' }}</td>
                            <td class="nowrap mono">{{ $a->created_at->format('m-d H:i') }}</td>
                            <td class="nowrap"><a class="btn btn-sm" href="{{ route('warehouses.assemblies.show', $a) }}">查看</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty"><div class="big">🧩</div>还没有组装 / 拆卸单</div></td></tr>
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
