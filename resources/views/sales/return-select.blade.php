@extends('layouts.app')

@section('title', '选择退货订单')
@section('desc', '仅已发货 / 已完成的销售订单可发起退货')

@section('actions')
    <a class="btn" href="{{ route('sales.returns') }}">退货单列表</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">可退货的销售订单</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>单号</th><th>客户</th><th>状态</th><th class="num">总额</th><th class="nowrap">下单日</th><th>操作</th></tr></thead>
                    <tbody>
                    @forelse ($orders as $o)
                        <tr>
                            <td class="mono strong">{{ $o->so_no }}</td>
                            <td>{{ $o->customer?->name ?? '散客' }}</td>
                            <td><span class="badge badge-{{ $o->statusTone($o->status) }}">{{ $o->statusLabel($o->status) }}</span></td>
                            <td class="num">¥{{ number_format($o->total_amount, 2) }}</td>
                            <td class="nowrap mono">{{ $o->order_date?->format('Y-m-d') }}</td>
                            <td class="nowrap"><a class="btn btn-sm btn-primary" href="{{ route('sales.returns.create', $o) }}">选择</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty"><div class="big">📝</div>暂无可退货的销售订单</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
