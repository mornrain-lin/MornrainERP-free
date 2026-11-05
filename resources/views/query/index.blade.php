@extends('layouts.app')

@section('title', '综合查询')
@section('desc', '跨模块统一检索：单据、库存、资金、流程一搜即得')

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="get" action="{{ route('query.index') }}" class="inline-form" style="gap:12px;flex-wrap:wrap">
                <input type="search" name="q" value="{{ $keyword }}" placeholder="输入单号 / SKU / 客户 / 手机号 / 关键词…"
                       style="flex:1;min-width:280px;padding:10px 14px" autofocus>
                <select name="scope" style="width:150px">
                    <option value="all" {{ $scope === 'all' ? 'selected' : '' }}>全部模块</option>
                    <option value="trade" {{ $scope === 'trade' ? 'selected' : '' }}>订单与购销</option>
                    <option value="retail" {{ $scope === 'retail' ? 'selected' : '' }}>零售</option>
                    <option value="finance" {{ $scope === 'finance' ? 'selected' : '' }}>资金</option>
                    <option value="workflow" {{ $scope === 'workflow' ? 'selected' : '' }}>审批流程</option>
                    <option value="base" {{ $scope === 'base' ? 'selected' : '' }}>基础资料</option>
                </select>
                <button class="btn btn-primary" type="submit">🔍 搜索</button>
                @if ($total > 0)
                    <a class="btn" href="{{ route('query.export', ['q' => $keyword, 'scope' => $scope]) }}">⇪ 导出结果</a>
                @endif
            </form>
            <div class="muted" style="font-size:12px;margin-top:8px">
                可检索：跨境订单 / 销售订单 / 报价单 / 退货单 / 采购单 / 零售单 / 会员 / 资金流水 / 审批流程 / 商品 / 客户 / 供应商 / 仓库；每组最多显示 8 条，导出不受限。
            </div>
        </div>
    </div>

    @if ($keyword === '')
        <div class="card">
            <div class="card-body">
                <div class="empty"><div class="big">🔍</div>输入关键词开始检索，例如：GW-、SO2026、客户名、手机号</div>
            </div>
        </div>
    @elseif ($total === 0)
        <div class="card">
            <div class="card-body">
                <div class="empty"><div class="big">🫥</div>未找到与「{{ $keyword }}」相关的内容，换个关键词或扩大范围试试</div>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body">
                <span class="muted">共命中 <strong class="up">{{ $total }}</strong> 条（关键词「{{ $keyword }}」）</span>
            </div>
        </div>

        @foreach ($groups as $key => $g)
            <div class="card">
                <div class="card-head">
                    <h2 class="card-title">{{ $g['icon'] }} {{ $g['title'] }}</h2>
                    <span class="badge badge-muted">{{ $g['shown'] }} 条</span>
                </div>
                <div class="card-body tight">
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead><tr><th>单号 / 名称</th><th>说明</th><th>状态</th><th>操作</th></tr></thead>
                            <tbody>
                            @foreach ($g['rows'] as $r)
                                <tr>
                                    <td class="strong">{{ $r['label'] }}</td>
                                    <td class="muted">{{ $r['desc'] }}</td>
                                    <td><span class="badge badge-muted">{{ $r['badge'] }}</span></td>
                                    <td class="nowrap"><a class="btn btn-sm" href="{{ $r['url'] }}">打开</a></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
