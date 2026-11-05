@extends('layouts.app')

@section('title', '报表设计')
@section('desc', '自定义数据源 / 展示列 / 筛选项，一键运行与导出 CSV')

@section('actions')
    <a class="btn btn-primary" href="{{ route('report-design.create') }}">新建报表</a>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-ok">{{ session('success') }}</div>
    @endif

    <div class="kpi-grid">
        <div class="kpi"><div class="kpi-label">报表总数</div><div class="kpi-value">{{ $stats['total'] }}</div><div class="kpi-foot">自定义模板</div></div>
        <div class="kpi"><div class="kpi-label">启用中</div><div class="kpi-value">{{ $stats['active'] }}</div><div class="kpi-foot">可运行报表</div></div>
        <div class="kpi"><div class="kpi-label">数据源</div><div class="kpi-value">{{ $stats['sources'] }}</div><div class="kpi-foot">订单/客户/商品/账号/流程</div></div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">报表模板</h2></div>
        <div class="card-body tight">
            @if ($templates->isEmpty())
                <div class="empty"><div class="big">📐</div>暂无报表模板，点击右上角「新建报表」开始设计</div>
            @else
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                        <tr><th>报表名称</th><th>编码</th><th>数据源</th><th>状态</th><th>创建人</th><th>操作</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($templates as $t)
                            <tr>
                                <td class="cell-main">
                                    <a class="link" href="{{ route('report-design.show', $t) }}">{{ $t->name }}</a>
                                    <div class="muted" style="font-size:12px">{{ $t->description ?: '—' }}</div>
                                </td>
                                <td><code>{{ $t->code }}</code></td>
                                <td><span class="badge badge-info">{{ \App\Services\ReportDesign\ReportDesignService::source($t->data_source)['label'] }}</span></td>
                                <td><span class="badge badge-{{ $t->statusTone() }}">{{ $t->statusLabel() }}</span></td>
                                <td class="muted">{{ optional($t->creator)->name ?? '—' }}</td>
                                <td class="ops">
                                    <a class="btn btn-sm" href="{{ route('report-design.show', $t) }}">运行</a>
                                    <a class="btn btn-sm" href="{{ route('report-design.edit', $t) }}">设计</a>
                                    <form method="POST" action="{{ route('report-design.toggle', $t) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm">{{ $t->is_active ? '停用' : '启用' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('report-design.destroy', $t) }}" style="display:inline"
                                          onsubmit="return confirm('确定删除报表「{{ $t->name }}」吗？')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">删除</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
