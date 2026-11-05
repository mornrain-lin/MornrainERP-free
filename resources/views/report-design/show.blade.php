@extends('layouts.app')

@section('title', '报表：'.$template->name)
@section('desc', '运行结果（'.($source['label'] ?? $template->data_source).' 数据源）')

@section('actions')
    <a class="btn" href="{{ route('report-design.index') }}">返回列表</a>
    <a class="btn" href="{{ route('report-design.edit', $template) }}">设计</a>
    <a class="btn btn-primary" href="{{ route('report-design.export', $template).(count($input) ? '?'.http_build_query($input) : '') }}">导出 CSV</a>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-ok">{{ session('success') }}</div>
    @endif

    <div class="kpi-grid">
        <div class="kpi"><div class="kpi-label">报表</div><div class="kpi-value">{{ $template->name }}</div><div class="kpi-foot">{{ $template->description ?: '—' }}</div></div>
        <div class="kpi"><div class="kpi-label">数据源</div><div class="kpi-value">{{ $source['label'] }}</div><div class="kpi-foot">{{ $template->code }}</div></div>
        <div class="kpi"><div class="kpi-label">行数</div><div class="kpi-value">{{ $rows->count() }}</div><div class="kpi-foot">{{ $rows->count() >= 200 ? '最多展示 200 行' : '当前结果' }}</div></div>
    </div>

    @if (count($template->filters))
        <div class="card">
            <div class="card-head"><h2 class="card-title">筛选条件</h2></div>
            <div class="card-body">
                <form method="GET" action="{{ route('report-design.show', $template) }}" class="form-grid">
                    @php
                        $dateFields = collect($source['fields'])->where('type', 'datetime')->pluck('field')->all();
                    @endphp
                    @foreach ($template->filters as $filter)
                        @php
                            $isDate = in_array($filter['field'], $dateFields);
                        @endphp
                        <div class="field">
                            <label class="field-label">{{ $filter['label'] }}</label>
                            @if ($filter['operator'] === 'between' && $isDate)
                                <div style="display:flex;gap:6px">
                                    <input type="date" name="{{ $filter['field'] }}_from" value="{{ $input[$filter['field'].'_from'] ?? '' }}">
                                    <span class="muted" style="align-self:center">至</span>
                                    <input type="date" name="{{ $filter['field'] }}_to" value="{{ $input[$filter['field'].'_to'] ?? '' }}">
                                </div>
                            @elseif ($filter['operator'] === 'between')
                                <div style="display:flex;gap:6px">
                                    <input type="number" name="{{ $filter['field'] }}_min" value="{{ $input[$filter['field'].'_min'] ?? '' }}" placeholder="最小值">
                                    <span class="muted" style="align-self:center">至</span>
                                    <input type="number" name="{{ $filter['field'] }}_max" value="{{ $input[$filter['field'].'_max'] ?? '' }}" placeholder="最大值">
                                </div>
                            @else
                                <input type="text" name="{{ $filter['field'] }}" value="{{ $input[$filter['field']] ?? '' }}">
                            @endif
                        </div>
                    @endforeach
                    <div class="field field-full">
                        <button type="submit" class="btn btn-primary">查询</button>
                        <a class="btn" href="{{ route('report-design.show', $template) }}">重置</a>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-head"><h2 class="card-title">结果明细</h2></div>
        <div class="card-body tight">
            @if ($rows->isEmpty())
                <div class="empty"><div class="big">📊</div>无匹配数据，请调整筛选条件</div>
            @else
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                        <tr>
                            <th>#</th>
                            @foreach ($columns as $col)
                                <th>{{ $col['label'] }}</th>
                            @endforeach
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($rows as $i => $row)
                            <tr>
                                <td class="num">{{ $i + 1 }}</td>
                                @foreach ($columns as $col)
                                    <td @if (in_array($col['type'], ['money', 'number'])) class="num" @endif>{{ \App\Services\ReportDesign\ReportDesignService::formatValue($row->{$col['field']} ?? null, $col['type']) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
