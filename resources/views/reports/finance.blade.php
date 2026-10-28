@extends('layouts.app')

@section('title', '财务报表')
@section('desc', $from . ' 至 ' . $to . ' · 收支 / 分类 / 账户 / 预付款')

@section('actions')
    <a class="btn" href="{{ route('reports.finance.export', ['date_from' => $from, 'date_to' => $to]) }}">↓ 导出收支流水</a>
@endsection

@section('content')
    {{-- 时间筛选 --}}
    <div class="card">
        <div class="card-body">
            <form method="get" action="{{ route('reports.finance') }}" class="filter-bar">
                <div class="field"><label>起始日期</label><input type="date" name="date_from" value="{{ $from }}"></div>
                <div class="field"><label>结束日期</label><input type="date" name="date_to" value="{{ $to }}"></div>
                <div class="field" style="flex-direction:row;gap:8px">
                    <button class="btn btn-primary" type="submit">生成报表</button>
                    <a class="btn" href="{{ route('reports.finance') }}">本月</a>
                </div>
            </form>
        </div>
    </div>

    {{-- 汇总 KPI --}}
    <div class="kpi-grid" style="margin-top:18px">
        <div class="kpi kpi-accent"><div class="kpi-label">期间总收入</div><div class="kpi-value up">¥{{ number_format($totalIncome, 2) }}</div></div>
        <div class="kpi kpi-accent amber"><div class="kpi-label">期间总支出</div><div class="kpi-value down">¥{{ number_format($totalExpense, 2) }}</div></div>
        <div class="kpi kpi-accent green"><div class="kpi-label">净现金流</div><div class="kpi-value {{ $net >= 0 ? 'up' : 'down' }}">¥{{ number_format($net, 2) }}</div><div class="kpi-foot">收入 − 支出</div></div>
        <div class="kpi kpi-accent"><div class="kpi-label">当前资金余额</div><div class="kpi-value">¥{{ number_format($activeBalance, 2) }}</div><div class="kpi-foot">{{ $accounts->where('is_active', true)->count() }} 个在用账户</div></div>
        <div class="kpi kpi-accent amber"><div class="kpi-label">销售应收余额</div><div class="kpi-value">¥{{ number_format($receivable, 2) }}</div><div class="kpi-foot">未回款合计</div></div>
        <div class="kpi kpi-accent"><div class="kpi-label">预付未核销</div><div class="kpi-value">¥{{ number_format($prepayOut, 2) }}</div><div class="kpi-foot">另预收 ¥{{ number_format($prepayIn, 2) }}</div></div>
    </div>

    <div class="grid grid-2" style="margin-top:18px">
        {{-- 按月收支趋势 --}}
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">按月收入 / 支出</h2>
                <div class="legend"><span><i style="background:#2f6fed"></i>收入</span><span><i style="background:#d9453d"></i>支出</span></div>
            </div>
            <div class="card-body">
                @php $maxMonth = max(1, collect($byMonth)->max(fn ($m) => max($m['income'], $m['expense']))); @endphp
                <div class="trend" style="height:150px;overflow-x:auto">
                    @forelse ($byMonth as $m)
                        <div class="trend-col" style="min-width:56px">
                            <div class="trend-bars">
                                <div class="trend-bar rev" style="height: {{ round($m['income'] / $maxMonth * 100) }}%" title="收入 ¥{{ number_format($m['income'], 2) }}"></div>
                                <div class="trend-bar" style="height: {{ round($m['expense'] / $maxMonth * 100) }}%;background:linear-gradient(180deg,#ff8a80,#d9453d)" title="支出 ¥{{ number_format($m['expense'], 2) }}"></div>
                            </div>
                            <div class="trend-label">{{ $m['month'] }}</div>
                        </div>
                    @empty
                        <div class="muted">区间内无流水</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- 账户余额 --}}
        <div class="card">
            <div class="card-head"><h2 class="card-title">资金账户余额</h2><span class="muted" style="font-size:12px">实时余额</span></div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>账户</th><th>类型</th><th class="num">余额</th><th>状态</th></tr></thead>
                        <tbody>
                        @forelse ($accounts as $acc)
                            <tr>
                                <td>{{ $acc->name }}</td>
                                <td>{{ \App\Models\FinanceAccount::typeLabel($acc->type) }}</td>
                                <td class="num strong">¥{{ number_format($acc->balance, 2) }}</td>
                                <td>@if ($acc->is_active)<span class="badge badge-success">在用</span>@else<span class="badge">停用</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">尚未建立资金账户</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- 分类汇总 --}}
    <div class="grid grid-2" style="margin-top:18px">
        <div class="card">
            <div class="card-head"><h2 class="card-title">收入分类</h2><span class="muted" style="font-size:12px">共 ¥{{ number_format($totalIncome, 2) }}</span></div>
            <div class="card-body">
                @php $incomeMax = max(1, collect($incomeCats)->max('amount')); @endphp
                @forelse ($incomeCats as $c)
                    <div style="margin-bottom:14px">
                        <div class="row-between" style="margin-bottom:5px">
                            <span>{{ $c['label'] }}</span>
                            <span class="mono">¥{{ number_format($c['amount'], 2) }}
                                <span class="muted">（{{ $c['share'] }}%）</span>
                            </span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ $c['amount'] > 0 ? max(2, round($c['amount'] / $incomeMax * 100)) : 0 }}%; background: #2f6fed"></div>
                        </div>
                    </div>
                @empty
                    <div class="muted">无数据</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">支出分类</h2><span class="muted" style="font-size:12px">共 ¥{{ number_format($totalExpense, 2) }}</span></div>
            <div class="card-body">
                @php $expenseMax = max(1, collect($expenseCats)->max('amount')); @endphp
                @forelse ($expenseCats as $c)
                    <div style="margin-bottom:14px">
                        <div class="row-between" style="margin-bottom:5px">
                            <span>{{ $c['label'] }}</span>
                            <span class="mono">¥{{ number_format($c['amount'], 2) }}
                                <span class="muted">（{{ $c['share'] }}%）</span>
                            </span>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ $c['amount'] > 0 ? max(2, round($c['amount'] / $expenseMax * 100)) : 0 }}%; background: #d9453d"></div>
                        </div>
                    </div>
                @empty
                    <div class="muted">无数据</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- 预付款未核销 --}}
    <div class="card" style="margin-top:18px">
        <div class="card-head">
            <h2 class="card-title">预付款 / 预收款未核销</h2>
            <a class="btn" href="{{ route('finance.prepayments') }}">前往核销</a>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>单号</th><th>方向</th><th>往来单位</th><th class="num">金额</th><th class="num">未核销</th><th>状态</th></tr></thead>
                    <tbody>
                    @forelse ($prepayPending as $p)
                        <tr>
                            <td class="mono">{{ $p->prepay_no }}</td>
                            <td>{{ \App\Models\FinancePrepayment::directionLabel($p->direction) }}</td>
                            <td>{{ $p->party_name }}</td>
                            <td class="num">¥{{ number_format($p->amount, 2) }}</td>
                            <td class="num strong">¥{{ number_format($p->remaining, 2) }}</td>
                            <td><span class="badge badge-{{ $p->statusTone($p->status) }}">{{ $p->statusLabel($p->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">没有未核销的预付款 / 预收款</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 近期流水 --}}
    <div class="card" style="margin-top:18px">
        <div class="card-head"><h2 class="card-title">近期收支流水</h2><span class="muted" style="font-size:12px">最近 {{ min(30, $flows->count()) }} 条</span></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>流水号</th><th>日期</th><th>方向</th><th>分类</th><th>账户</th><th class="num">金额</th><th>备注</th></tr></thead>
                    <tbody>
                    @forelse ($flows->take(30) as $f)
                        <tr>
                            <td class="mono">{{ $f->flow_no }}</td>
                            <td>{{ $f->occurred_on?->format('Y-m-d') }}</td>
                            <td><span class="badge badge-{{ $f->direction === 'income' ? 'success' : 'danger' }}">{{ \App\Models\FinanceFlow::directionLabel($f->direction) }}</span></td>
                            <td>{{ \App\Models\FinanceFlow::categoryLabel($f->category) }}</td>
                            <td>{{ $f->account?->name }}</td>
                            <td class="num strong {{ $f->direction === 'income' ? 'up' : 'down' }}">{{ $f->direction === 'income' ? '+' : '−' }}¥{{ number_format($f->amount, 2) }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($f->remark, 24) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">区间内暂无流水</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
