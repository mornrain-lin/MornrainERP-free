@extends('layouts.app')

@section('title', '对账中心')
@section('desc', '比对系统账面余额与实盘余额，自动标记差异')

@section('actions')
    <a class="btn" href="{{ route('finance.index') }}">财务总览</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">发起一次对账</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('finance.reconciliations.store') }}" class="inline-form" style="flex-wrap:wrap;gap:10px">
                @csrf
                <select name="account_id" style="width:180px" required>
                    <option value="">选择账户</option>
                    @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}（账面 ¥{{ number_format($a->balance, 2) }}）</option>@endforeach
                </select>
                <input type="number" name="actual_balance" step="0.01" placeholder="实盘余额" style="width:150px" required>
                <input type="text" name="note" placeholder="备注，如银行流水截至日" style="width:220px">
                <button class="btn btn-primary" type="submit">对账</button>
            </form>
            <div class="muted" style="font-size:12px;margin-top:8px">差异 = 实盘余额 − 系统账面余额；为 0 记「已平账」，否则记「有差异」并列出差额。</div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">对账记录</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr><th class="nowrap">对账日</th><th>账户</th><th class="num">账面余额</th><th class="num">实盘余额</th><th class="num">差异</th><th>结果</th><th>操作人</th><th>备注</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($list as $r)
                        <tr>
                            <td class="nowrap mono">{{ $r->period_end?->format('Y-m-d') }}</td>
                            <td>{{ $r->account?->name ?? '—' }}</td>
                            <td class="num">¥{{ number_format($r->system_balance, 2) }}</td>
                            <td class="num">¥{{ number_format($r->actual_balance, 2) }}</td>
                            <td class="num strong {{ $r->difference == 0 ? 'muted' : ($r->difference > 0 ? 'up' : 'down') }}">
                                {{ $r->difference > 0 ? '+' : '' }}¥{{ number_format($r->difference, 2) }}
                            </td>
                            <td><span class="badge badge-{{ $r->statusTone($r->status) }}">{{ $r->statusLabel($r->status) }}</span></td>
                            <td class="muted">{{ $r->creator?->name ?? '—' }}</td>
                            <td class="muted" style="font-size:12px">{{ \Illuminate\Support\Str::limit($r->note, 24) ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty"><div class="big">⚖</div>暂无对账记录</div></td></tr>
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
