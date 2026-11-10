@extends('layouts.app')

@section('title', '升级到收费版 · ' . $name)

@section('content')
<div class="card" style="max-width:760px;margin:24px auto;">
    <div class="alert alert-ok" style="margin:0 0 16px;">
        <span>🔒</span>
        <span>「{{ $name }}」属于晨雨ERP <strong>收费版</strong> 模块。</span>
    </div>

    <h2 style="margin:0 0 8px;">解锁 {{ $name }}</h2>
    <p class="muted" style="line-height:1.7;">
        当前安装为<strong>免费开源版</strong>，仅包含进销存、基础财务、订单、库存与基础报表等核心能力。
        收费模块以独立私有包形式交付，需购买授权后安装并激活，源代码不随公开仓库分发。
    </p>

    <div class="kpi-grid" style="margin:18px 0;">
        <div class="kpi"><div class="kpi-val">14</div><div class="kpi-label">收费模块</div></div>
        <div class="kpi"><div class="kpi-val">私有</div><div class="kpi-label">代码交付</div></div>
        <div class="kpi"><div class="kpi-val">激活码</div><div class="kpi-label">解锁门禁</div></div>
    </div>

    <ol style="line-height:1.9;color:#444;">
        <li>前往 <a href="https://erp.mornrain.com" target="_blank" rel="noopener">晨雨ERP 官网</a> / 联系商务获取收费版授权与私有仓库访问权限；</li>
        <li>按 <code>PREMIUM.md</code> 配置私有仓库令牌并执行安装脚本；</li>
        <li>在「系统 → 激活码管理」粘贴激活码，对应模块即自动解锁。</li>
    </ol>

    <div style="display:flex;gap:10px;margin-top:18px;">
        <a class="btn btn-primary" href="{{ route('license.index') }}">前往激活码管理</a>
        <a class="btn" href="{{ route('dashboard') }}">返回概览</a>
    </div>
</div>
@endsection
