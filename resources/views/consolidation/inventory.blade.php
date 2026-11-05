@extends('layouts.app')

@section('title', '内部未实现利润登记')
@section('desc', '组织间存货交易 · 期末未售出部分的毛利须合并抵消')

@section('actions')
    <a class="btn btn-primary" href="{{ route('consolidation.index') }}">返回合并报表</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">登记内部存货交易</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('consolidation.inventory.store') }}" class="inline-form" style="flex-wrap:wrap;gap:12px;align-items:flex-end">
                @csrf
                <label>卖方组织
                    <select name="seller_org_id" style="width:160px">
                        @foreach ($orgs as $o)<option value="{{ $o->id }}">{{ str_repeat('— ', $o->level) }}{{ $o->name }}</option>@endforeach
                    </select>
                </label>
                <label>买方组织
                    <select name="buyer_org_id" style="width:160px">
                        @foreach ($orgs as $o)<option value="{{ $o->id }}">{{ str_repeat('— ', $o->level) }}{{ $o->name }}</option>@endforeach
                    </select>
                </label>
                <label>售价
                    <input type="number" step="0.01" name="amount" value="0" style="width:120px">
                </label>
                <label>成本
                    <input type="number" step="0.01" name="cost" value="0" style="width:120px">
                </label>
                <label>期末未售比例
                    <input type="number" step="0.0001" name="unsold_ratio" value="1" style="width:120px">
                </label>
                <label>起
                    <input type="date" name="period_from" value="{{ now()->startOfMonth()->toDateString() }}">
                </label>
                <label>止
                    <input type="date" name="period_to" value="{{ now()->endOfMonth()->toDateString() }}">
                </label>
                <label>备注
                    <input type="text" name="note" style="width:160px" placeholder="选填">
                </label>
                <button class="btn btn-primary btn-sm" type="submit">登记</button>
                <span class="muted small">未实现利润 = (售价 − 成本) × 未售比例，自动计算</span>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">已登记的内部存货交易</h2><span class="muted small">仅合并范围内双方在场时才抵消</span></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>期间</th><th>卖方</th><th>买方</th>
                        <th class="num">售价</th><th class="num">成本</th><th class="num">毛利</th>
                        <th class="num">未售比例</th><th class="num">未实现利润</th><th>备注</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($entries as $e)
                        <tr>
                            <td class="mono">{{ $e->period_from?->format('Y-m-d') }} ~ {{ $e->period_to?->format('Y-m-d') }}</td>
                            <td>{{ $e->seller->name ?? '—' }}</td>
                            <td>{{ $e->buyer->name ?? '—' }}</td>
                            <td class="num">¥{{ number_format($e->amount, 2) }}</td>
                            <td class="num">¥{{ number_format($e->cost, 2) }}</td>
                            <td class="num">¥{{ number_format($e->profit, 2) }}</td>
                            <td class="num">{{ number_format($e->unsold_ratio * 100, 1) }}%</td>
                            <td class="num down">− ¥{{ number_format($e->unrealized_profit, 2) }}</td>
                            <td class="muted">{{ $e->note }}</td>
                            <td>
                                <form method="post" action="{{ route('consolidation.inventory.destroy', $e) }}" style="display:inline">
                                    @csrf @method('delete')
                                    <button class="btn btn-sm btn-ghost" type="submit" onclick="return confirm('确认删除该登记？')">删除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="empty">还没有登记任何内部存货交易</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
