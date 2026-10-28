@extends('layouts.app')

@section('title', '账务台账')
@section('desc', '账户明细账 · 科目总账 · 期间流水查询导出')

@section('actions')
    <a class="btn" href="{{ route('finance.flows') }}">收支流水</a>
    <a class="btn btn-primary" href="{{ route('finance.ledger.export', ['date_from' => $from, 'date_to' => $to, 'account_id' => $accountId]) }}">⇪ 导出 CSV</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="get" action="{{ route('finance.ledger') }}" class="inline-form" style="flex-wrap:wrap;gap:10px">
                <label>从 <input type="date" name="date_from" value="{{ $from }}"></label>
                <label>到 <input type="date" name="date_to" value="{{ $to }}"></label>
                <select name="account_id" style="width:160px">
                    <option value="">全部账户</option>
                    @foreach ($accounts as $a)
                        <option value="{{ $a->id }}" {{ (string) $accountId === (string) $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">查询</button>
                <span class="muted" style="font-size:12px">默认统计本月</span>
            </form>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi">
            <div class="kpi-label">期间收入合计</div>
            <div class="kpi-value up">¥{{ number_format($sumIn, 2) }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">期间支出合计</div>
            <div class="kpi-value down">¥{{ number_format($sumOut, 2) }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">期间净额</div>
            <div class="kpi-value {{ $sumIn - $sumOut >= 0 ? 'up' : 'down' }}">¥{{ number_format($sumIn - $sumOut, 2) }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">期末总余额</div>
            <div class="kpi-value">¥{{ number_format($ledgerRows->sum('closing'), 2) }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">账户明细账</h2><span class="muted">{{ $from }} ~ {{ $to }}</span></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr><th>账户</th><th>类型</th><th class="num">期初余额</th><th class="num">期间收入</th><th class="num">期间支出</th><th class="num">期末余额</th><th class="num">流水数</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($ledgerRows as $row)
                        <tr>
                            <td class="strong">{{ $row['account']->name }}</td>
                            <td class="muted">{{ $row['account']->typeLabel() }}</td>
                            <td class="num">¥{{ number_format($row['opening'], 2) }}</td>
                            <td class="num up">¥{{ number_format($row['income'], 2) }}</td>
                            <td class="num down">¥{{ number_format($row['expense'], 2) }}</td>
                            <td class="num strong">¥{{ number_format($row['closing'], 2) }}</td>
                            <td class="num muted">{{ $row['flows'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><div class="big">🏦</div>暂无资金账户</div></td></tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="2" class="strong">合计</td>
                        <td class="num strong">¥{{ number_format($ledgerRows->sum('opening'), 2) }}</td>
                        <td class="num strong up">¥{{ number_format($ledgerRows->sum('income'), 2) }}</td>
                        <td class="num strong down">¥{{ number_format($ledgerRows->sum('expense'), 2) }}</td>
                        <td class="num strong">¥{{ number_format($ledgerRows->sum('closing'), 2) }}</td>
                        <td class="num strong">{{ $ledgerRows->sum('flows') }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">科目总账</h2><span class="muted">按收支分类汇总期间发生额</span></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>科目</th><th>方向</th><th class="num">发生额</th><th class="num">笔数</th><th style="width:220px">占比</th></tr></thead>
                    <tbody>
                    @forelse ($byCategory as $c)
                        <tr>
                            <td class="strong">{{ \App\Models\FinanceFlow::categoryLabel($c->category) }}</td>
                            <td><span class="badge badge-{{ $c->direction === 'income' ? 'success' : 'warn' }}">{{ \App\Models\FinanceFlow::directionLabel($c->direction) }}</span></td>
                            <td class="num strong">¥{{ number_format($c->total, 2) }}</td>
                            <td class="num muted">{{ $c->cnt }}</td>
                            <td>
                                <div class="bar-track"><div class="bar-fill" style="width: {{ round((float) $c->total / $maxCatTotal * 100, 1) }}%"></div></div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><div class="big">📊</div>该期间暂无流水</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">期间流水明细</h2><span class="muted">最近 30 笔 / 页</span></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr><th class="nowrap">日期</th><th>单号</th><th>账户</th><th>科目</th><th class="num">金额</th><th>备注</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($flows as $f)
                        <tr>
                            <td class="nowrap mono">{{ $f->occurred_on?->format('Y-m-d') }}</td>
                            <td class="mono">{{ $f->flow_no }}</td>
                            <td>{{ $f->account?->name ?? '—' }}</td>
                            <td><span class="badge badge-{{ $f->direction === 'income' ? 'success' : 'warn' }}">{{ $f->categoryLabel($f->category) }}</span></td>
                            <td class="num strong {{ $f->direction === 'income' ? 'up' : 'down' }}">{{ $f->direction === 'income' ? '+' : '−' }}¥{{ number_format($f->amount, 2) }}</td>
                            <td class="muted">{{ \Illuminate\Support\Str::limit($f->remark, 40) ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty"><div class="big">📒</div>该期间暂无流水</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($flows->hasPages())
            <div class="pagination-wrap">{{ $flows->links() }}</div>
        @endif
    </div>
@endsection
