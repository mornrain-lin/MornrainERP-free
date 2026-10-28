@extends('layouts.app')

@section('title', $name)
@section('desc', '模块说明 · ' . ($status === 'ready' ? '已上线' : '规划中'))

@section('actions')
    <a class="btn" href="{{ route('roadmap') }}">🗺 产品路线图</a>
    <a class="btn" href="{{ route('dashboard') }}">← 返回概览</a>
@endsection

@section('content')
    <div class="hub-head">
        <div class="hub-logo">{{ $icon }}</div>
        <div>
            <h2 class="hub-title">{{ $name }}
                @if ($status === 'ready')
                    <span class="badge badge-success">已上线</span>
                @else
                    <span class="badge badge-warn">规划中</span>
                @endif
            </h2>
            <p class="hub-desc">{{ $desc ?: '该模块正在产品路线图中，以下为规划说明。' }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">规划功能</h2>
            <div class="card-desc">该模块将包含以下能力</div>
        </div>
        <div class="card-body">
            @if (!empty($features))
                <div class="feat-grid">
                    @foreach ($features as $f)
                        <div class="feat"><span class="feat-dot">✓</span>{{ $f }}</div>
                    @endforeach
                </div>
            @else
                <div class="empty">暂未填写功能清单</div>
            @endif
        </div>
    </div>

    <div class="alert alert-ok" style="margin-top:16px">
        <span>ℹ</span>
        <span>本页为「模块落地页」。港湾ERP 按阶段逐步上线：已上线模块在侧边栏直链进入；
        规划中模块先在此展示功能规划，对应阶段实现后自动切换为可用页面。</span>
    </div>
@endsection
