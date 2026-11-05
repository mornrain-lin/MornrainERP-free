@extends('layouts.app')

@section('title', '合并报表')
@section('desc', '跨组织汇总 · 内部往来抵消 · 集团口径经营结果')

@section('actions')
    <a class="btn" href="{{ route('intercompany.index') }}">内部往来登记</a>
    <a class="btn" href="{{ route('consolidation.inventory') }}">未实现利润登记</a>
    <a class="btn btn-primary" href="{{ route('consolidation.export', ['root_org_id' => $rootId, 'date_from' => $report['from'], 'date_to' => $report['to']]) }}">⇪ 导出 CSV</a>
@endsection

@section('content')
    @php
        $t = $report['totals'];
        $monthPeaks = collect($report['monthly'])->map(fn ($m) => max((float) $m['income'], (float) $m['expense']))->all();
        $maxMonth = max(1.0, ...($monthPeaks ?: [1.0]));
    @endphp

    <div class="card">
        <div class="card-body">
            <form method="get" action="{{ route('consolidation.index') }}" class="inline-form" style="flex-wrap:wrap;gap:10px">
                <label>合并主体
                    <select name="root_org_id" style="width:200px">
                        @foreach ($candidates as $c)
                            <option value="{{ $c->id }}" {{ (string) $rootId === (string) $c->id ? 'selected' : '' }}>
                                {{ str_repeat('— ', $c->level) }}{{ $c->name }}（{{ $c->typeLabel() }}）
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>从 <input type="date" name="date_from" value="{{ $report['from'] }}"></label>
                <label>到 <input type="date" name="date_to" value="{{ $report['to'] }}"></label>
                <button class="btn btn-primary btn-sm" type="submit">生成报表</button>
                <span class="muted small">范围＝该组织及其全部下级 · 本位币 {{ $report['currency'] }}</span>
            </form>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi">
            <div class="kpi-label">合并收入（抵消后）</div>
            <div class="kpi-value up">¥{{ number_format($t['income'], 2) }}</div>
            <div class="kpi-foot">毛口径 ¥{{ number_format($t['gross_income'], 2) }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">合并支出（抵消后）</div>
            <div class="kpi-value down">¥{{ number_format($t['expense'], 2) }}</div>
            <div class="kpi-foot">毛口径 ¥{{ number_format($t['gross_expense'], 2) }}</div>
        </div>
        <div class="kpi kpi-accent {{ $t['net'] >= 0 ? 'green' : 'red' }}">
            <div class="kpi-label">合并净额</div>
            <div class="kpi-value {{ $t['net'] >= 0 ? 'up' : 'down' }}">¥{{ number_format($t['net'], 2) }}</div>
            <div class="kpi-foot">含 {{ $t['org_count'] }} 个组织 · {{ $t['flow_count'] }} 笔流水</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">期末资金余额</div>
            <div class="kpi-value">¥{{ number_format($t['balance'], 2) }}</div>
            <div class="kpi-foot">时点值 · 各组织账户直接相加</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">少数股东损益</div>
            <div class="kpi-value">¥{{ number_format($t['minority_interest'], 2) }}</div>
            <div class="kpi-foot">非控股股东应享</div>
        </div>
        <div class="kpi kpi-accent {{ ($t['net_attributable'] ?? 0) >= 0 ? 'green' : 'red' }}">
            <div class="kpi-label">归母净利润</div>
            <div class="kpi-value {{ ($t['net_attributable'] ?? 0) >= 0 ? 'up' : 'down' }}">¥{{ number_format($t['net_attributable'] ?? $t['net'], 2) }}</div>
            <div class="kpi-foot">归属于母公司股东</div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">抵消桥：毛口径 → 合并数</h2>
            <span class="muted small">仅当内部往来两端都在合并范围内才抵消</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr><th>口径</th><th class="num">收入</th><th class="num">支出</th><th class="num">净额</th></tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td class="strong">各组织简单相加（毛口径）</td>
                        <td class="num up">¥{{ number_format($t['gross_income'], 2) }}</td>
                        <td class="num down">¥{{ number_format($t['gross_expense'], 2) }}</td>
                        <td class="num">¥{{ number_format($t['gross_net'], 2) }}</td>
                    </tr>
                    <tr>
                        <td class="strong">减：内部往来抵消</td>
                        <td class="num down">− ¥{{ number_format($t['eliminated_income'], 2) }}</td>
                        <td class="num down">− ¥{{ number_format($t['eliminated_expense'], 2) }}</td>
                        <td class="num muted">{{ $t['eliminated_count'] }} 笔</td>
                    </tr>
                    <tr>
                        <td class="strong">合并数</td>
                        <td class="num up strong">¥{{ number_format($t['income'], 2) }}</td>
                        <td class="num down strong">¥{{ number_format($t['expense'], 2) }}</td>
                        <td class="num strong {{ $t['net'] >= 0 ? 'up' : 'down' }}">¥{{ number_format($t['net'], 2) }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">组织明细</h2>
            <span class="muted small">{{ $report['from'] }} ~ {{ $report['to'] }}</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>组织</th><th>层级</th>
                        <th class="num">毛收入</th><th class="num">毛支出</th>
                        <th class="num">抵消收入</th><th class="num">抵消支出</th>
                        <th class="num">合并收入</th><th class="num">合并支出</th>
                        <th class="num">合并净额</th><th class="num">期末余额</th><th class="num">流水</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($report['rows'] as $r)
                        <tr>
                            <td class="strong">{{ str_repeat('— ', $r['org']->level) }}{{ $r['org']->name }}</td>
                            <td class="muted">{{ $r['org']->typeLabel() }}</td>
                            <td class="num">¥{{ number_format($r['gross_income'], 2) }}</td>
                            <td class="num">¥{{ number_format($r['gross_expense'], 2) }}</td>
                            <td class="num muted">− ¥{{ number_format($r['eliminated_income'], 2) }}</td>
                            <td class="num muted">− ¥{{ number_format($r['eliminated_expense'], 2) }}</td>
                            <td class="num up">¥{{ number_format($r['income'], 2) }}</td>
                            <td class="num down">¥{{ number_format($r['expense'], 2) }}</td>
                            <td class="num strong {{ $r['net'] >= 0 ? 'up' : 'down' }}">¥{{ number_format($r['net'], 2) }}</td>
                            <td class="num">¥{{ number_format($r['balance'], 2) }}</td>
                            <td class="num muted">{{ $r['flow_count'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><div class="empty"><div class="big">🏛</div>该范围内没有启用的组织</div></td></tr>
                    @endforelse
                    </tbody>
                    @if ($report['rows'])
                        <tfoot>
                        <tr>
                            <td colspan="2" class="strong">合计</td>
                            <td class="num strong">¥{{ number_format($t['gross_income'], 2) }}</td>
                            <td class="num strong">¥{{ number_format($t['gross_expense'], 2) }}</td>
                            <td class="num strong muted">− ¥{{ number_format($t['eliminated_income'], 2) }}</td>
                            <td class="num strong muted">− ¥{{ number_format($t['eliminated_expense'], 2) }}</td>
                            <td class="num strong up">¥{{ number_format($t['income'], 2) }}</td>
                            <td class="num strong down">¥{{ number_format($t['expense'], 2) }}</td>
                            <td class="num strong">¥{{ number_format($t['net'], 2) }}</td>
                            <td class="num strong">¥{{ number_format($t['balance'], 2) }}</td>
                            <td class="num strong">{{ $t['flow_count'] }}</td>
                        </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">少数股东权益</h2>
            <span class="muted small">非全资子公司按集团持股比例拆分归母与少数股东损益</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>组织</th><th>层级</th>
                        <th class="num">集团持股%</th><th class="num">子公司净利润</th><th class="num">少数股东损益</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($report['minority']['items'] as $m)
                        <tr>
                            <td class="strong">{{ $m['org_name'] }}</td>
                            <td class="muted">{{ $m['type'] }}</td>
                            <td class="num">{{ number_format($m['group_ownership_pct'], 2) }}%</td>
                            <td class="num">¥{{ number_format($m['net'], 2) }}</td>
                            <td class="num down">− ¥{{ number_format($m['minority_net'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">范围内组织均为全资持有，无少数股东损益</div></td></tr>
                    @endforelse
                    @if ($report['minority']['total'] != 0)
                        <tfoot>
                        <tr>
                            <td colspan="4" class="strong">少数股东损益合计</td>
                            <td class="num down strong">− ¥{{ number_format($report['minority']['total'], 2) }}</td>
                        </tr>
                        </tfoot>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">内部未实现利润（存货）</h2>
            <span class="muted small">期末仍留存在下游的内部交易毛利需从合并中抵消</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>卖方</th><th>买方</th>
                        <th class="num">售价</th><th class="num">成本</th><th class="num">毛利</th>
                        <th class="num">未售比例</th><th class="num">未实现利润（抵消）</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($report['unrealized']['items'] as $u)
                        <tr>
                            <td>{{ $u['seller'] }}</td>
                            <td>{{ $u['buyer'] }}</td>
                            <td class="num">¥{{ number_format($u['amount'], 2) }}</td>
                            <td class="num">¥{{ number_format($u['cost'], 2) }}</td>
                            <td class="num">¥{{ number_format($u['profit'], 2) }}</td>
                            <td class="num">{{ number_format($u['unsold_ratio'] * 100, 1) }}%</td>
                            <td class="num down">− ¥{{ number_format($u['unrealized_profit'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty">本期无内部存货交易，或未登记未实现利润</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="small muted" style="margin-top:8px">
                期初未实现利润 <strong>¥{{ number_format($report['unrealized']['opening'], 2) }}</strong> ·
                期末未实现利润 <strong>− ¥{{ number_format($report['unrealized']['closing'], 2) }}</strong> ·
                本期实现（加回合并净利润） <strong class="up">+ ¥{{ number_format($report['unrealized']['realized'], 2) }}</strong>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">合并快照</h2>
            <span class="muted small">固化本期合并结果，便于审计留档与跨期对比</span>
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('consolidation.snapshot.store') }}" style="display:inline-flex;gap:10px;align-items:center;flex-wrap:wrap">
                @csrf
                <input type="hidden" name="root_org_id" value="{{ $rootId }}">
                <input type="hidden" name="date_from" value="{{ $report['from'] }}">
                <input type="hidden" name="date_to" value="{{ $report['to'] }}">
                <button class="btn btn-sm btn-primary" type="submit">保存当前合并快照</button>
                <a class="btn btn-sm" href="{{ route('consolidation.snapshots', ['root_org_id' => $rootId]) }}">查看历史快照</a>
            </form>
        </div>
    </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-head"><h2 class="card-title">分类科目（抵消后）</h2></div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>方向</th><th>科目</th><th class="num">毛额</th><th class="num">抵消</th><th class="num">合并数</th></tr></thead>
                        <tbody>
                        @forelse ($report['categories'] as $c)
                            <tr>
                                <td><span class="badge {{ $c['direction'] === 'income' ? 'badge-success' : 'badge-warn' }}">{{ $c['direction_label'] }}</span></td>
                                <td class="strong">{{ $c['label'] }}</td>
                                <td class="num muted">¥{{ number_format($c['gross'], 2) }}</td>
                                <td class="num muted">− ¥{{ number_format($c['eliminated'], 2) }}</td>
                                <td class="num strong">¥{{ number_format($c['net'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty">本期无流水</div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">月度趋势（抵消后）</h2></div>
            <div class="card-body">
                @forelse ($report['monthly'] as $m)
                    <div style="margin-bottom:12px">
                        <div class="row-between small"><span class="strong">{{ $m['month'] }}</span><span class="muted">净额 ¥{{ number_format($m['net'], 2) }}</span></div>
                        <div class="small muted">收入 ¥{{ number_format($m['income'], 2) }}</div>
                        <div class="bar-track"><div class="bar-fill" style="width:{{ round($m['income'] / $maxMonth * 100, 1) }}%"></div></div>
                        <div class="small muted" style="margin-top:4px">支出 ¥{{ number_format($m['expense'], 2) }}</div>
                        <div class="bar-track"><div class="bar-fill" style="width:{{ round($m['expense'] / $maxMonth * 100, 1) }}%;background:var(--danger)"></div></div>
                    </div>
                @empty
                    <div class="empty">无期间数据</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">本期抵消的内部往来</h2>
            <span class="badge badge-info">{{ $t['eliminated_count'] }} 笔</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>单号</th><th>日期</th><th>付款方</th><th>收款方</th><th>类型</th><th class="num">金额</th><th class="num">抵消支出</th><th class="num">抵消收入</th></tr></thead>
                    <tbody>
                    @forelse ($report['eliminations'] as $e)
                        <tr>
                            <td class="mono">{{ $e['txn']->txn_no }}</td>
                            <td class="muted">{{ $e['txn']->occurred_on?->format('Y-m-d') }}</td>
                            <td>{{ $e['from'] }}</td>
                            <td>{{ $e['to'] }}</td>
                            <td class="muted">{{ $e['category'] }}</td>
                            <td class="num strong">¥{{ number_format($e['amount'], 2) }}</td>
                            <td class="num down">− ¥{{ number_format($e['eliminated_expense'], 2) }}</td>
                            <td class="num down">− ¥{{ number_format($e['eliminated_income'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty">本期没有需要抵消的内部往来</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($report['external'])
        <div class="alert alert-err">
            <strong>有 {{ count($report['external']) }} 笔内部往来的一端在合并范围外</strong>，按口径不抵消、视同对外往来。
            若需完整集团口径，请把合并主体上移到覆盖这些组织的共同上级。
            <div class="small muted" style="margin-top:6px">
                @foreach ($report['external'] as $e)
                    {{ $e['txn']->txn_no }} {{ $e['from'] }} → {{ $e['to'] }} ¥{{ number_format($e['amount'], 2) }}@if (! $loop->last) ；@endif
                @endforeach
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-head"><h2 class="card-title">口径说明</h2></div>
        <div class="card-body small muted" style="line-height:1.9">
            <div>· <strong>合并范围</strong>：所选组织及其整棵下级子树中「启用」的组织；停用组织的历史数据不纳入。</div>
            <div>· <strong>抵消规则</strong>：仅「已登记且两端都在合并范围内」的内部往来会成对剔除其镜像流水；只有一端在范围内时保留该端，视同对外往来；手工流水一律不抵消。</div>
            <div>· <strong>币别</strong>：按流水币别折算为 {{ $report['currency'] }}；当前系统统一 CNY，未配置汇率的外币会直接报错而非静默算错。</div>
            <div>· <strong>资金余额</strong>：时点值，各组织账户余额直接相加；内部往来在余额层面天然净额为零，不额外抵消。</div>
            <div>· <strong>少数股东</strong>：子公司按「集团持股比例」（组织路径上各祖先 ownership_pct 之积）拆分；归母净利润 = 合并净利润 − 少数股东损益。</div>
            <div>· <strong>内部未实现利润</strong>：组织间存货交易期末仍留存在下游的部分，其内含毛利须从合并净利润与存货中抵减；仅当买卖双方都在合并范围内才抵消，只有一端在内视为对外交易（已实现）。</div>
            <div>· <strong>跨期抵消</strong>：取上一期快照留存的「期末未实现利润」作为本期期初；期初大于期末（存货对外售出）的差额即本期实现，加回合并净利润。</div>
            <div>· <strong>独立性</strong>：合并报表及快照都是只读视图，不改动任何组织的独立账簿；各组织原有的总览、台账、财务报表口径保持不变。</div>
            <div>· <strong>期间</strong>：按流水发生日（occurred_on）归属，单次跨度上限 {{ config('consolidation.max_period_days', 366) }} 天。</div>
        </div>
    </div>
@endsection
