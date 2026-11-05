@extends('layouts.app')

@section('title', '合并快照 · ' . ($snapshot->period_from?->format('Y-m-d') ?? ''))
@section('desc', '已固化的合并结果（只读回放）')

@section('actions')
    <a class="btn" href="{{ route('consolidation.snapshots', ['root_org_id' => $snapshot->root_org_id]) }}">快照列表</a>
    <a class="btn btn-primary" href="{{ route('consolidation.index', ['root_org_id' => $snapshot->root_org_id]) }}">返回实时报表</a>
@endsection

@section('content')
    @php
        $t = $data['totals'] ?? [];
        $rootName = $data['root_name'] ?? '—';
    @endphp

    <div class="alert alert-info">
        这是 <strong>{{ $rootName }}</strong> 在 <strong>{{ $data['from'] }} ~ {{ $data['to'] }}</strong> 的合并快照（生成于 {{ $snapshot->created_at?->format('Y-m-d H:i') }}），数据为固化值，不随后续记账变化。
    </div>

    <div class="kpi-grid">
        <div class="kpi">
            <div class="kpi-label">合并净利润（含少数股东）</div>
            <div class="kpi-value">¥{{ number_format((float) ($t['net'] ?? 0), 2) }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">少数股东损益</div>
            <div class="kpi-value">¥{{ number_format((float) ($t['minority_interest'] ?? 0), 2) }}</div>
        </div>
        <div class="kpi kpi-accent {{ ($t['net_attributable'] ?? 0) >= 0 ? 'green' : 'red' }}">
            <div class="kpi-label">归母净利润</div>
            <div class="kpi-value {{ ($t['net_attributable'] ?? 0) >= 0 ? 'up' : 'down' }}">¥{{ number_format((float) ($t['net_attributable'] ?? 0), 2) }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">期末资金余额</div>
            <div class="kpi-value">¥{{ number_format((float) ($t['balance'] ?? 0), 2) }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">组织明细（固化）</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>组织</th><th>层级</th>
                        <th class="num">合并收入</th><th class="num">合并支出</th><th class="num">合并净额</th>
                        <th class="num">集团持股%</th><th class="num">少数股东损益</th><th class="num">期末余额</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse (($data['rows'] ?? []) as $r)
                        <tr>
                            <td class="strong">{{ $r['org_name'] }}</td>
                            <td class="muted">{{ $r['type'] }}</td>
                            <td class="num">¥{{ number_format((float) ($r['income'] ?? 0), 2) }}</td>
                            <td class="num">¥{{ number_format((float) ($r['expense'] ?? 0), 2) }}</td>
                            <td class="num {{ ($r['net'] ?? 0) >= 0 ? 'up' : 'down' }}">¥{{ number_format((float) ($r['net'] ?? 0), 2) }}</td>
                            <td class="num">{{ number_format((float) ($r['group_ownership_pct'] ?? 100), 2) }}%</td>
                            <td class="num down">− ¥{{ number_format((float) ($r['minority_net'] ?? 0), 2) }}</td>
                            <td class="num">¥{{ number_format((float) ($r['balance'] ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty">无组织明细</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">少数股东权益（固化）</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>组织</th><th>层级</th><th class="num">集团持股%</th><th class="num">子公司净利润</th><th class="num">少数股东损益</th></tr></thead>
                    <tbody>
                    @forelse (($data['minority']['items'] ?? []) as $m)
                        <tr>
                            <td class="strong">{{ $m['org_name'] }}</td>
                            <td class="muted">{{ $m['type'] }}</td>
                            <td class="num">{{ number_format((float) ($m['group_ownership_pct'] ?? 100), 2) }}%</td>
                            <td class="num">¥{{ number_format((float) ($m['net'] ?? 0), 2) }}</td>
                            <td class="num down">− ¥{{ number_format((float) ($m['minority_net'] ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">无少数股东损益</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">内部未实现利润（固化）</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>卖方</th><th>买方</th><th class="num">售价</th><th class="num">成本</th><th class="num">毛利</th><th class="num">未售比例</th><th class="num">未实现利润</th></tr></thead>
                    <tbody>
                    @forelse (($data['unrealized']['items'] ?? []) as $u)
                        <tr>
                            <td>{{ $u['seller'] }}</td>
                            <td>{{ $u['buyer'] }}</td>
                            <td class="num">¥{{ number_format((float) ($u['amount'] ?? 0), 2) }}</td>
                            <td class="num">¥{{ number_format((float) ($u['cost'] ?? 0), 2) }}</td>
                            <td class="num">¥{{ number_format((float) ($u['profit'] ?? 0), 2) }}</td>
                            <td class="num">{{ number_format((float) ($u['unsold_ratio'] ?? 0) * 100, 1) }}%</td>
                            <td class="num down">− ¥{{ number_format((float) ($u['unrealized_profit'] ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty">无未实现利润</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="small muted" style="margin-top:8px">
                期初未实现利润 <strong>¥{{ number_format((float) (($data['unrealized']['opening'] ?? 0)), 2) }}</strong> ·
                期末未实现利润 <strong>− ¥{{ number_format((float) (($data['unrealized']['closing'] ?? 0)), 2) }}</strong> ·
                本期实现 <strong class="up">+ ¥{{ number_format((float) (($data['unrealized']['realized'] ?? 0)), 2) }}</strong>
            </div>
        </div>
    </div>
@endsection
