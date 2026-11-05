@extends('layouts.app')

@section('title', '激活码管理')
@section('desc', '激活专业版 / 企业版，解锁企业套件全部功能')

@section('content')
<div class="card">
    <div class="card-head">
        <h2 class="card-title">当前套餐</h2>
    </div>
    <div class="card-body">
        @if (session('success'))
            <div class="alert alert-ok">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-err">
                @foreach ($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif

        <div class="kpi-grid">
            <div class="kpi">
                <div class="kpi-label">当前套餐</div>
                <div class="kpi-value">
                    {{ strtoupper($plan) }}
                    @if ($plan === 'free')
                        <span class="badge badge-muted">免费版</span>
                    @else
                        <span class="badge badge-ok">{{ $plan === 'pro' ? '专业版' : '企业版' }}</span>
                    @endif
                </div>
                @if (!empty($info))
                    <div class="kpi-foot">有效期至 {{ $info['expires_at'] }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2 class="card-title">激活 / 更换激活码</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('license.activate') }}" class="form-grid">
            @csrf
            <div class="field" style="grid-column:1/-1">
                <label>激活码 <span class="req">*</span></label>
                <input type="text" name="code" placeholder="MRERP1.PRO.YYYYMMDD.XXXXXXXX.CHECK" required
                       style="font-family:monospace" autocomplete="off">
                <small class="muted">格式：MRERP1.{PRO|ENT}.到期日.随机串.校验位。企业版可解锁全部企业套件。</small>
            </div>
            <div class="field" style="grid-column:1/-1">
                <button class="btn btn-primary" type="submit">激活</button>
            </div>
        </form>

        @if ($plan !== 'free')
            <form method="POST" action="{{ route('license.destroy') }}" style="margin-top:16px">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm" type="submit">解除激活（回落免费版）</button>
            </form>
        @endif
    </div>
</div>
@endsection
