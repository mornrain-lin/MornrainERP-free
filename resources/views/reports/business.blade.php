@extends('layouts.app')

@section('title', '经营报表')
@section('desc', $from . ' 至 ' . $to . ' · 销售 / 采购 / 库存 / 零售多维分析')

@section('content')
    {{-- 时间筛选 --}}
    <div class="card">
        <div class="card-body">
            <form method="get" action="{{ route('reports.business') }}" class="filter-bar">
                <div class="field"><label>起始日期</label><input type="date" name="date_from" value="{{ $from }}"></div>
                <div class="field"><label>结束日期</label><input type="date" name="date_to" value="{{ $to }}"></div>
                <div class="field" style="flex-direction:row;gap:8px">
                    <button class="btn btn-primary" type="submit">生成报表</button>
                    <a class="btn" href="{{ route('reports.business') }}">本月</a>
                </div>
            </form>
        </div>
    </div>

    {{-- 汇总 KPI --}}
    <div class="kpi-grid" style="margin-top:18px">
        <div class="kpi kpi-accent"><div class="kpi-label">销售订单</div><div class="kpi-value">{{ number_format($saleOrders->count()) }}</div><div class="kpi-foot">共 ¥{{ number_format($saleTotal, 2) }}</div></div>
        <div class="kpi kpi-accent green"><div class="kpi-label">已回款</div><div class="kpi-value up">¥{{ number_format($salePaid, 2) }}</div></div>
        <div class="kpi kpi-accent amber"><div class="kpi-label">应收余额</div><div class="kpi-value">¥{{ number_format($saleReceivable, 2) }}</div><div class="kpi-foot">回款率 {{ $saleTotal > 0 ? round($salePaid / $saleTotal * 100, 1) : 0 }}%</div></div>
        <div class="kpi kpi-accent"><div class="kpi-label">采购订单</div><div class="kpi-value">{{ number_format($purchaseOrders->count()) }}</div><div class="kpi-foot">共 ¥{{ number_format($purchaseTotal, 2) }}</div></div>
        <div class="kpi kpi-accent"><div class="kpi-label">库存总值</div><div class="kpi-value">¥{{ number_format($stockValue, 2) }}</div><div class="kpi-foot">{{ number_format($stockQty) }} 件在库</div></div>
        <div class="kpi kpi-accent amber"><div class="kpi-label">库存预警</div><div class="kpi-value">{{ $lowStockCount }}</div><div class="kpi-foot">低于安全库存 SKU</div></div>
        <div class="kpi kpi-accent"><div class="kpi-label">零售开单</div><div class="kpi-value">{{ number_format($retailSales->count()) }}</div><div class="kpi-foot">¥{{ number_format($retailAmount, 2) }}</div></div>
        <div class="kpi kpi-accent"><div class="kpi-label">零售退货</div><div class="kpi-value">{{ $retailReturns->count() }}</div><div class="kpi-foot">会员 {{ $memberCount }} 人</div></div>
    </div>

    {{-- 按月趋势 --}}
    <div class="grid grid-2" style="margin-top:18px">
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">按月销售金额</h2>
                <div class="legend"><span><i style="background:#2f6fed"></i>销售额</span></div>
            </div>
            <div class="card-body">
                @php $saleMax = max(1, collect($saleByMonth)->max('amount')); @endphp
                <div class="trend" style="height:140px;overflow-x:auto">
                    @forelse ($saleByMonth as $m)
                        <div class="trend-col" style="min-width:56px">
                            <div class="trend-bars">
                                <div class="trend-bar rev" style="height: {{ round($m['amount'] / $saleMax * 100) }}%" title="¥{{ number_format($m['amount'], 2) }}"></div>
                            </div>
                            <div class="trend-label">{{ $m['month'] }}</div>
                        </div>
                    @empty
                        <div class="muted">区间内无销售订单</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h2 class="card-title">按月采购金额</h2>
                <div class="legend"><span><i style="background:#12a06a"></i>采购额</span></div>
            </div>
            <div class="card-body">
                @php $purchaseMax = max(1, collect($purchaseByMonth)->max('amount')); @endphp
                <div class="trend" style="height:140px;overflow-x:auto">
                    @forelse ($purchaseByMonth as $m)
                        <div class="trend-col" style="min-width:56px">
                            <div class="trend-bars">
                                <div class="trend-bar pro" style="height: {{ round($m['amount'] / $purchaseMax * 100) }}%" title="¥{{ number_format($m['amount'], 2) }}"></div>
                            </div>
                            <div class="trend-label">{{ $m['month'] }}</div>
                        </div>
                    @empty
                        <div class="muted">区间内无采购订单</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- 客户 / 供应商 Top --}}
    <div class="grid grid-2" style="margin-top:18px">
        <div class="card">
            <div class="card-head"><h2 class="card-title">客户销售 Top 10</h2></div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>客户</th><th class="num">订单</th><th class="num">金额</th><th class="num">已回款</th></tr></thead>
                        <tbody>
                        @forelse ($byCustomer as $c)
                            <tr>
                                <td>{{ $c['name'] }}</td>
                                <td class="num">{{ $c['orders'] }}</td>
                                <td class="num">¥{{ number_format($c['total'], 2) }}</td>
                                <td class="num">¥{{ number_format($c['paid'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">区间内无销售数据</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">供应商采购 Top 10</h2></div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>供应商</th><th class="num">订单</th><th class="num">金额</th><th class="num">已入库</th></tr></thead>
                        <tbody>
                        @forelse ($bySupplier as $s)
                            <tr>
                                <td>{{ $s['name'] }}</td>
                                <td class="num">{{ $s['orders'] }}</td>
                                <td class="num">¥{{ number_format($s['total'], 2) }}</td>
                                <td class="num">{{ $s['received'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">区间内无采购数据</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- 库存分布 --}}
    <div class="grid grid-2" style="margin-top:18px">
        <div class="card">
            <div class="card-head"><h2 class="card-title">分仓库存金额</h2><span class="muted" style="font-size:12px">按成本价计</span></div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>仓库</th><th class="num">SKU 数</th><th class="num">库存量</th><th class="num">金额</th></tr></thead>
                        <tbody>
                        @forelse ($byWarehouse as $w)
                            <tr>
                                <td>{{ $w['name'] }}</td>
                                <td class="num">{{ $w['skus'] }}</td>
                                <td class="num">{{ number_format($w['qty']) }}</td>
                                <td class="num strong">¥{{ number_format($w['value'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">尚未分配仓库库存</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">基础资料规模</h2></div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead><tr><th>资料</th><th class="num">数量</th></tr></thead>
                        <tbody>
                            <tr><td>客户</td><td class="num">{{ number_format($counts['customers']) }}</td></tr>
                            <tr><td>供应商</td><td class="num">{{ number_format($counts['suppliers']) }}</td></tr>
                            <tr><td>商品 SKU</td><td class="num">{{ number_format($counts['products']) }}</td></tr>
                            <tr><td>零售会员</td><td class="num">{{ number_format($memberCount) }}</td></tr>
                            <tr><td>系统账号</td><td class="num">{{ number_format($counts['users']) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
