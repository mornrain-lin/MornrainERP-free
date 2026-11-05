<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>登录 · 晨雨ERP</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="auth-body">
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="brand-logo">GW</div>
            <div>
                <div class="brand-name">晨雨ERP</div>
                @php $company = \App\Models\Setting::get('company_name', ''); @endphp
                <div class="brand-sub">{{ $company && $company !== '晨雨ERP' ? $company : '全功能企业一体化管理平台' }}</div>
            </div>
        </div>

        <h1 class="auth-title">登录后台</h1>
        <p class="auth-desc">请使用管理员分配的邮箱账号登录</p>

        @if ($errors->any())
            <div class="alert alert-err">
                <span>!</span>
                <div>{{ $errors->first() }}</div>
            </div>
        @endif

        @if (session('ok'))
            <div class="alert alert-ok"><span>✓</span><span>{{ session('ok') }}</span></div>
        @endif

        <form method="post" action="{{ route('login.attempt') }}">
            @csrf
            <div class="field">
                <label for="email">登录邮箱</label>
                <input type="text" id="email" name="email" value="{{ old('email') }}" autocomplete="username" autofocus required>
            </div>
            <div class="field">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
            </div>

            <label class="auth-remember">
                <input type="checkbox" name="remember" value="1"> 记住我（7 天免登录）
            </label>

            <button class="btn btn-primary auth-submit" type="submit">登录</button>
        </form>

        <div class="auth-foot">
            忘记密码请联系管理员重置 · © {{ date('Y') }} 晨雨ERP
        </div>
    </div>
</div>
</body>
</html>
