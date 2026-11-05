@extends('layouts.app')

@section('title', '报价单')
@section('desc', '向客户出具报价，可一键转销售订单')

@section('actions')
    <a class="btn btn-primary" href="{{ route('sales.quotes.create') }}">＋ 新建报价单</a>
    <a class="btn" href="{{ route('sales.orders') }}">销售订单</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">报价单列表</h2>
            <form method="get" action="{{ route('sales.quotes') }}" class="inline-form">
                <select name="status" style="width:120px">
                    <option value="">全部状态</option>
                    @foreach (['draft' => '草稿', 'sent' => '已发送', 'accepted' => '已接受', 'rejected' => '已拒绝', 'converted' => '已转订单', 'expired' => '已过期'] as $k => $v)
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
                    <tr><th>单号</th><th>客户</th><th>状态</th><th class="num">报价总额</th><th class="nowrap">有效期至</th><th class="nowrap">创建日</th><th>操作</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($list as $q)
                        <tr>
                            <td class="mono strong">{{ $q->quote_no }}</td>
                            <td>{{ $q->customer?->name ?? '散客' }}</td>
                            <td><span class="badge badge-{{ $q->statusTone($q->status) }}">{{ $q->statusLabel($q->status) }}</span></td>
                            <td class="num strong">¥{{ number_format($q->total_amount, 2) }}</td>
                            <td class="nowrap mono">{{ $q->valid_until?->format('Y-m-d') ?? '—' }}</td>
                            <td class="nowrap mono">{{ $q->created_at->format('Y-m-d') }}</td>
                            <td class="nowrap"><a class="btn btn-sm" href="{{ route('sales.quotes.show', $q) }}">查看</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><div class="big">📄</div>暂无报价单</div></td></tr>
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
