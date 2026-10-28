<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '概览') · 港湾ERP</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">GW</div>
            <div class="brand-text">
                <span class="brand-name">港湾ERP</span>
                @php $company = \App\Models\Setting::get('company_name', ''); @endphp
                <span class="brand-sub">{{ $company && $company !== '港湾ERP' ? $company : '一体化企业管理平台' }}</span>
            </div>
        </div>

        <nav class="nav">
            @foreach (\App\Support\MenuConfig::groups() as $group)
                @php
                    $visible = collect($group['items'] ?? [])->filter(function ($it) {
                        if (empty($it['admin'])) {
                            return true;
                        }
                        // 带 permission 键的模块：按细粒度权限可见（管理员与超级角色自动放行）
                        if (!empty($it['permission'])) {
                            return auth()->user()?->hasPermission($it['permission']) ?? false;
                        }

                        return auth()->user()?->isAdmin() ?? false;
                    });
                @endphp
                @if ($visible->isNotEmpty())
                    <div class="nav-group">
                        <div class="nav-title">{{ $group['title'] }}</div>
                        @foreach ($visible as $it)
                            @php
                                $external = !empty($it['external']);
                                $href = !empty($it['url'])
                                    ? $it['url']
                                    : ((!empty($it['route']) && Route::has($it['route']))
                                        ? route($it['route'])
                                        : (!empty($it['hub']) ? route('module.hub', $it['hub']) : '#'));
                                $active = !$external && ((!empty($it['route']) && Route::has($it['route']) && request()->routeIs($it['route']))
                                    || (!empty($it['hub']) && request()->routeIs('module.hub') && request()->route('key') === $it['hub']));
                            @endphp
                            <a class="nav-item {{ $active ? 'active' : '' }}" href="{{ $href }}" @if ($external) target="_blank" rel="noopener" @endif>
                                <span class="ico">{{ $it['icon'] }}</span>
                                <span class="nav-label">{{ $it['name'] }}</span>
                                @if (!empty($it['status']) && $it['status'] !== 'ready')
                                    <span class="nav-soon">规划中</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </nav>

        <div class="sidebar-foot">
            v0.9 · 全模块一体化<br>
            登录：{{ auth()->user()?->email }}<br>
            © {{ date('Y') }} 港湾ERP
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <h1 class="page-title">@yield('title', '概览')</h1>
                @hasSection('desc')
                    <div class="page-desc">@yield('desc')</div>
                @endif
            </div>
            <div class="topbar-right">
                @yield('actions')
                <div class="user-chip">
                    <span class="avatar">{{ mb_substr(auth()->user()?->name ?? 'U', 0, 1) }}</span>
                    <div class="user-meta">
                        <span class="strong">{{ auth()->user()?->name }}</span>
                        <span class="muted">{{ auth()->user()?->roleLabel() }}</span>
                    </div>
                    <a class="btn btn-sm" href="{{ route('password.form') }}">改密码</a>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-sm" type="submit">退出</button>
                    </form>
                </div>
            </div>
        </header>

        <div class="content">
            @if (session('ok'))
                <div class="alert alert-ok"><span>✓</span><span>{{ session('ok') }}</span></div>
            @endif
            @if (session('err'))
                <div class="alert alert-err"><span>!</span><span>{{ session('err') }}</span></div>
            @endif
            @if ($errors->any())
                <div class="alert alert-err">
                    <span>!</span>
                    <div>
                        <div class="strong">请检查以下问题：</div>
                        <ul class="err-list">
                            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
