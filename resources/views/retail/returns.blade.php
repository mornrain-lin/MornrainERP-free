@extends('layouts.app')

@section('title', '零售退货')
@section('desc', '退货退款记录 · 库存回补 · 原路退款')

@section('actions')
    <a class="btn" href="{{ route('retail.sales') }}">零售单</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">退货记录</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr><th>退货单号</th><th>原零售单</th><th>会员</th><th class="num">退款金额</th><th>状态</th><th>原因</th><th>操作人</th><th class="nowrap">时间</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($list as $r)
                        <tr>
                            <td class="mono strong">{{ $r->return_no }}</td>
                            <td class="mono"><a href="{{ route('retail.sales.show', $r->retail_sale_id) }}">{{ $r->sale?->sale_no ?? '—' }}</a></td>
                            <td>{{ $r->sale?->member?->name ?? '散客' }}</td>
                            <td class="num strong down">¥{{ number_format($r->total, 2) }}</td>
                            <td><span class="badge badge-{{ $r->status === 'canceled' ? 'badge-muted' : 'badge-success' }}">{{ $r->statusLabel($r->status) }}</span></td>
                            <td class="muted" style="font-size:12px">{{ $r->reason ?: '—' }}</td>
                            <td class="muted">{{ $r->handler?->name ?? '—' }}</td>
                            <td class="nowrap mono">{{ $r->created_at->format('m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty"><div class="big">↩</div>暂无退货记录</div></td></tr>
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
