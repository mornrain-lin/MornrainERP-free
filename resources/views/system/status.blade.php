@extends('layouts.app')

@section('title', '系统状态')
@section('desc', '运行环境 / 数据规模 / 存储占用 / 套餐额度')

@section('actions')
    <form method="post" action="{{ route('system.clear-cache') }}" onsubmit="return confirm('确认清理应用缓存？清理后首次访问会稍慢。')">
        @csrf
        <button class="btn" type="submit">🧹 清理缓存</button>
    </form>
@endsection

@section('content')
    {{-- 运行环境 --}}
    <div class="grid grid-2">
        <div class="card">
            <div class="card-head"><h2 class="card-title">运行环境</h2></div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <tbody>
                        @foreach ($env as $label => $value)
                            <tr><td class="muted" style="width:120px">{{ $label }}</td><td class="strong">{{ $value }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- 套餐额度 --}}
        <div class="card">
            <div class="card-head"><h2 class="card-title">套餐额度</h2><span class="badge badge-info">{{ $quotaInfo['plan'] }}</span></div>
            <div class="card-body">
                @if ($quotaInfo['limited'])
                    <div class="quota-bar-wrap">
                        <div class="quota-bar-head">
                            <span>本月已用订单</span>
                            <span class="mono">{{ $quotaInfo['used'] }} / {{ $quotaInfo['limit'] }}</span>
                        </div>
                        <div class="quota-bar">
                            <div class="quota-fill {{ $quotaInfo['pct'] >= 90 ? 'full' : '' }}" style="width: {{ $quotaInfo['pct'] }}%"></div>
                        </div>
                    </div>
                    <div class="row-between" style="margin-top:10px">
                        <span class="muted" style="font-size:12px">剩余 {{ $quotaInfo['remaining'] }} 单（自然月重置）</span>
                        <span class="muted" style="font-size:12px">用量 {{ $quotaInfo['pct'] }}%</span>
                    </div>
                @else
                    <div class="muted">当前套餐不限订单量。</div>
                @endif
            </div>
        </div>
    </div>

    {{-- 数据规模 --}}
    <div class="card" style="margin-top:18px">
        <div class="card-head"><h2 class="card-title">数据规模</h2><span class="muted" style="font-size:12px">各核心表记录数</span></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>数据表</th><th>说明</th><th class="num">记录数</th></tr></thead>
                    <tbody>
                    @foreach ($tables as $key => $row)
                        <tr>
                            <td class="mono">{{ $key }}</td>
                            <td>{{ $row['label'] }}</td>
                            <td class="num strong">{{ number_format($row['count']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- 存储占用 --}}
    <div class="grid grid-2" style="margin-top:18px">
        <div class="card">
            <div class="card-head"><h2 class="card-title">存储占用</h2></div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <tbody>
                        @foreach ($storage as $label => $size)
                            <tr><td class="muted" style="width:120px">{{ $label }}</td><td class="strong">{{ $size }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">维护提示</h2></div>
            <div class="card-body">
                <ul style="margin:0;padding-left:18px;line-height:2;font-size:13px;color:var(--ink-2)">
                    <li>定期在「操作审计」检查异常登录与批量删除。</li>
                    <li>生产环境请将 <span class="mono">APP_DEBUG</span> 设为 <span class="mono">false</span>。</li>
                    <li>上线前执行一次「清理缓存」，避免陈旧配置/路由缓存。</li>
                    <li>SQLite 适合小团队；数据量上来后按 README 切 MySQL。</li>
                </ul>
            </div>
        </div>
    </div>
@endsection
