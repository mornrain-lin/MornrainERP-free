@extends('layouts.app')

@section('title', '流程目录')
@section('desc', '覆盖 ' . $total . ' 种电子流程 · 一键发起，按审批节点自动流转')

@section('actions')
    <a class="btn" href="{{ route('workflows.my') }}">我发起的</a>
    <a class="btn btn-primary" href="{{ route('workflows.todo') }}">我的待办</a>
@endsection

@section('content')
    <div class="kpi-grid">
        <div class="kpi kpi-accent">
            <div class="kpi-label">电子流程总数</div>
            <div class="kpi-value">{{ $total }}</div>
            <div class="kpi-foot">跨采购 / 财务 / 人事 / 行政 / 销售 / 仓库 / 生产 / 零售 / 项目 / 通用</div>
        </div>
        <div class="kpi kpi-accent green">
            <div class="kpi-label">流程分类</div>
            <div class="kpi-value">{{ count($cats) }}</div>
            <div class="kpi-foot">每个分类下含若干标准审批流</div>
        </div>
        <div class="kpi kpi-accent amber">
            <div class="kpi-label">使用方式</div>
            <div class="kpi-value" style="font-size:18px">发起 → 审批 → 办结</div>
            <div class="kpi-foot">节点链可配置，新增流程无需改代码</div>
        </div>
    </div>

    @foreach ($cats as $catKey => $cat)
        <div class="card" style="margin-top:18px">
            <div class="card-head">
                <h2 class="card-title">{{ $cat['label'] }}</h2>
                <div class="card-desc">{{ count($cat['items']) }} 个流程</div>
            </div>
            <div class="card-body">
                <div class="wf-grid">
                    @foreach ($cat['items'] as $key => $def)
                        <div class="wf-item">
                            <div class="wf-item-head">
                                <span class="strong">{{ $def['name'] }}</span>
                                <a class="btn btn-sm btn-primary" href="{{ route('workflows.create', $key) }}">发起</a>
                            </div>
                            <div class="wf-desc">{{ $def['desc'] ?? '' }}</div>
                            <div class="wf-nodes">
                                @foreach ($def['nodes'] ?? [] as $i => $node)
                                    <span class="chip">{{ $node }}</span>@if ($i < count($def['nodes']) - 1)<span class="wf-arrow">→</span>@endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
@endsection
