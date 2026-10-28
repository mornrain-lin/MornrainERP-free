@extends('layouts.app')

@section('title', '财务总览')
@section('desc', '资金账户 · 本月收支 · 净额 · 资金占用')

@section('actions')
    <a class="btn btn-primary" href="{{ route('finance.flows') }}">＋ 记一笔流水</a>
    <a class="btn" href="{{ route('finance.reconciliations') }}">对账</a>
@endsection

@section('content')
    <div class="kpi-grid">
        <div class="kpi kpi-accent">
            <div class="kpi-label">账户总余额</div>
            <div class="kpi-value">¥{{ number_format($totalBalance, 2) }}</div>
            <div class="kpi-foot">{{ $accounts->count() }} 个启用账户</div>
        </div>
        <div class="kpi kpi-accent red">
            <div class="kpi-label">本月收入</div>
            <div class="kpi-value up">¥{{ number_format($income, 2) }}</div>
            <div class="kpi-foot">{{ now()->format('Y 年 m 月') }}</div>
        </div>
        <div class="kpi kpi-accent green">
            <div class="kpi-label">本月支出</div>
            <div class="kpi-value down">¥{{ number_format($expense, 2) }}</div>
            <div class="kpi-foot">{{ now()->format('Y 年 m 月') }}</div>
        </div>
        <div class="kpi kpi-accent amber">
            <div class="kpi-label">本月净额</div>
            <div class="kpi-value {{ ($income - $expense) >= 0 ? 'up' : 'down' }}">
                {{ ($income - $expense) >= 0 ? '+' : '' }}¥{{ number_format($income - $expense, 2) }}
            </div>
            <div class="kpi-foot">收入 − 支出</div>
        </div>
    </div>

    @if ($openPrepay > 0)
        <div class="alert alert-ok">
            <span>💰</span>
            <span>供应商预付款未核销占用资金 <span class="strong up">¥{{ number_format($openPrepay, 2) }}</span>（付出去尚未冲抵的成本）</span>
        </div>
    @endif

    <div class="grid grid-2" style="margin-top:18px">
        <div class="card">
            <div class="card-head"><h2 class="card-title">资金账户</h2><a class="muted" href="{{ route('finance.accounts') }}" style="font-size:12px">管理 →</a></div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>账户</th><th>类型</th><th class="num">余额</th></tr></thead>
                        <tbody>
                        @forelse ($accounts as $a)
                            <tr>
                                <td class="strong">{{ $a->name }}</td>
                                <td class="muted">{{ \App\Models\FinanceAccount::typeLabel($a->type) }}</td>
                                <td class="num strong">{{ $a->balance < 0 ? '' : '' }}¥{{ number_format($a->balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">还没有资金账户，先去<a href="{{ route('finance.accounts') }}">新建</a></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">最近对账</h2><a class="muted" href="{{ route('finance.reconciliations') }}" style="font-size:12px">全部 →</a></div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>账户</th><th class="num">账面</th><th class="num">实盘</th><th>结果</th></tr></thead>
                        <tbody>
                        @forelse ($recons as $r)
                            <tr>
                                <td>{{ $r->account?->name ?? '—' }}</td>
                                <td class="num">¥{{ number_format($r->system_balance, 2) }}</td>
                                <td class="num">¥{{ number_format($r->actual_balance, 2) }}</td>
                                <td><span class="badge badge-{{ $r->statusTone($r->status) }}">{{ $r->statusLabel($r->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">暂无对账记录</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">近期流水</h2><a class="muted" href="{{ route('finance.flows') }}" style="font-size:12px">全部 →</a></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr><th class="nowrap">日期</th><th>单号</th><th>账户</th><th>分类</th><th class="num">金额</th><th>操作人</th><th>备注</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($recent as $f)
                        <tr>
                            <td class="nowrap mono">{{ $f->occurred_on?->format('m-d') }}</td>
                            <td class="mono">{{ $f->flow_no }}</td>
                            <td class="muted">{{ $f->account?->name ?? '—' }}</td>
                            <td>{{ \App\Models\FinanceFlow::categoryLabel($f->category) }}</td>
                            <td class="num strong {{ $f->direction === 'income' ? 'up' : 'down' }}">
                                {{ $f->direction === 'income' ? '+' : '−' }}¥{{ number_format($f->amount, 2) }}
                            </td>
                            <td class="muted">{{ $f->creator?->name ?? '—' }}</td>
                            <td class="muted" style="font-size:12px">{{ \Illuminate\Support\Str::limit($f->remark, 20) ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><div class="big">¥</div>还没有流水，记第一笔开始记账</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
