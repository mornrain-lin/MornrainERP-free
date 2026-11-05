@extends('layouts.app')

@section('title', '产品路线图')
@section('desc', '晨雨ERP 全模块地图 · 上线进度与规划')

@section('actions')
    <a class="btn" href="{{ route('dashboard') }}">← 返回概览</a>
@endsection

@section('content')
    <div class="kpi-grid">
        <div class="kpi kpi-accent">
            <div class="kpi-label">已上线模块</div>
            <div class="kpi-value up">{{ $ready }}</div>
            <div class="kpi-foot">侧边栏直连可用</div>
        </div>
        <div class="kpi kpi-accent amber">
            <div class="kpi-label">规划中模块</div>
            <div class="kpi-value">{{ $planned }}</div>
            <div class="kpi-foot">按阶段陆续上线</div>
        </div>
        <div class="kpi kpi-accent green">
            <div class="kpi-label">模块总数</div>
            <div class="kpi-value">{{ $ready + $planned }}</div>
            <div class="kpi-foot">一体化企业管理平台</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <span class="strong">整体上线进度</span>
                <span class="muted" style="font-size:12px">{{ $ready }} / {{ $ready + $planned }}</span>
            </div>
            <div style="height:12px;background:var(--muted-soft);border-radius:99px;overflow:hidden">
                <div style="height:100%;width:{{ $ready + $planned > 0 ? round($ready / ($ready + $planned) * 100) : 0 }}%;background:linear-gradient(90deg,var(--brand),var(--brand-dark));border-radius:99px"></div>
            </div>
        </div>
    </div>

    @foreach ($groups as $group)
        @php
            $items = collect($group['items'] ?? [])->filter(
                fn ($it) => empty($it['admin']) || auth()->user()?->isAdmin()
            );
            $gReady = $items->filter(fn ($it) => ($it['status'] ?? 'planned') === 'ready')->count();
        @endphp
        @if ($items->isNotEmpty())
            <div class="card">
                <div class="card-head">
                    <h2 class="card-title">{{ $group['title'] }}</h2>
                    <span class="muted" style="font-size:12px">{{ $gReady }} / {{ $items->count() }} 已上线</span>
                </div>
                <div class="card-body">
                    <div class="rm-grid">
                        @foreach ($items as $it)
                            @php
                                $isReady = ($it['status'] ?? 'planned') === 'ready';
                                $href = (!empty($it['route']) && Route::has($it['route']))
                                    ? route($it['route'])
                                    : (!empty($it['hub']) ? route('module.hub', $it['hub']) : '#');
                            @endphp
                            <a class="rm-card {{ $isReady ? 'rm-ready' : 'rm-planned' }}" href="{{ $href }}">
                                <div class="rm-card-head">
                                    <span class="rm-icon">{{ $it['icon'] ?? '◻' }}</span>
                                    <span class="rm-name">{{ $it['name'] }}</span>
                                    @if ($isReady)
                                        <span class="badge badge-success">已上线</span>
                                    @else
                                        <span class="badge badge-warn">规划中</span>
                                    @endif
                                </div>
                                @unless ($isReady)
                                    <p class="rm-desc">{{ $it['meta']['desc'] ?? '' }}</p>
                                    @if (!empty($it['meta']['features']))
                                        <div class="rm-feats">
                                            @foreach ($it['meta']['features'] as $f)
                                                <span class="rm-feat">{{ $f }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                @else
                                    <p class="rm-desc muted">点击进入模块</p>
                                @endunless
                                @if (!empty($it['admin']))
                                    <span class="rm-admin">管理员</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    <div class="alert alert-ok" style="margin-top:16px">
        <span>ℹ</span>
        <span>晨雨ERP 采用「先落地页、后上线」的节奏：规划中模块先展示功能规划，对应阶段实现后自动切换为可用页面。
        企业套件（CRM / PM / MES / OA / EHR / AI / 商城）将在此路线图上陆续点亮。</span>
    </div>
@endsection
