<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '概览') · 晨雨ERP</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo">GW</div>
            <div class="brand-text">
                <span class="brand-name">晨雨ERP</span>
                @php $company = \App\Models\Setting::get('company_name', ''); @endphp
                <span class="brand-sub">{{ $company && $company !== '晨雨ERP' ? $company : '一体化企业管理平台' }}</span>
            </div>
        </div>

        <nav class="nav">
            @foreach (\App\Support\MenuConfig::groups() as $group)
                @php
                    $visible = collect($group['items'] ?? [])
                        ->filter(function ($it) {
                            if (empty($it['admin'])) {
                                return true;
                            }
                            // 带 permission 键的模块：按细粒度权限可见（管理员与超级角色自动放行）
                            if (!empty($it['permission'])) {
                                return auth()->user()?->hasPermission($it['permission']) ?? false;
                            }

                            return auth()->user()?->isAdmin() ?? false;
                        })
                        ->filter(function ($it) {
                            // 锁定项（收费模块未安装私有包）始终展示为升级入口
                            if (! empty($it['locked'])) {
                                return true;
                            }

                            // 套餐门禁：要求高于当前套餐的模块在菜单中隐藏（fail-open 由 LicenseService 保证）
                            if (empty($it['plan'])) {
                                return true;
                            }

                            return \App\Services\LicenseService::rank(\App\Services\LicenseService::plan())
                                >= \App\Services\LicenseService::rank($it['plan']);
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
                                @if (!empty($it['locked']))
                                    <span class="nav-soon">升级</span>
                                @elseif (!empty($it['status']) && $it['status'] !== 'ready')
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
            © {{ date('Y') }} 晨雨ERP
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
                @if (auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('organizations'))
                    @php
                        $canAll = \App\Support\OrgContext::canViewAll(auth()->user());
                        $accessibleIds = \App\Support\OrgContext::accessibleOrgIds(auth()->user());
                        $orgs = \App\Models\Organization::whereIn('id', $accessibleIds)->orderBy('level')->orderBy('sort')->orderBy('id')->get();
                        $current = \App\Support\OrgContext::isAll() ? 'all' : \App\Support\OrgContext::id();
                    @endphp
                    <form method="post" action="{{ route('org.switch') }}" class="org-switch" title="切换当前组织作用域">
                        @csrf
                        <span class="org-switch-label">组织</span>
                        <select name="org_id" onchange="this.form.submit()">
                            @if ($canAll)
                                <option value="all" @selected($current === 'all')>全部组织</option>
                            @endif
                            @foreach ($orgs as $o)
                                <option value="{{ $o->id }}" @selected($current === $o->id)>{{ str_repeat('　', $o->level) }}{{ $o->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
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
