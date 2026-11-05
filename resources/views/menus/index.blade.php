@extends('layouts.app')

@section('title', '菜单管理')
@section('desc', '自定义后台菜单：显隐 / 排序 / 自定义链接（路由权限不受影响）')

@section('actions')
    <form method="post" action="{{ route('menus.reset') }}"
          onsubmit="return confirm('恢复默认将清空全部显隐、排序与自定义链接，确认？')" style="display:inline">@csrf
        <button class="btn" type="submit">恢复默认</button>
    </form>
@endsection

@section('content')
    <form method="post" action="{{ route('menus.store') }}">
        @csrf
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">分组与菜单</h2>
                <button class="btn btn-primary btn-sm" type="submit">保存配置</button>
            </div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                        <tr>
                            <th style="width:90px">分组序号</th>
                            <th>分组 / 菜单</th>
                            <th style="width:110px">分组显隐</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($groups as $group)
                            @php($title = (string) ($group['title'] ?? ''))
                            <tr>
                                <td>
                                    <input type="number" name="order[{{ $title }}]" min="0" max="999"
                                           value="{{ $positions[$title] ?? '' }}" style="width:70px">
                                </td>
                                <td class="strong">{{ $title }}</td>
                                <td>
                                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                                        <input type="checkbox" name="hidden_groups[]" value="{{ $title }}"
                                            {{ in_array($title, $override['hidden_groups'], true) ? 'checked' : '' }}>
                                        <span class="muted">隐藏</span>
                                    </label>
                                </td>
                            </tr>
                            @foreach ($group['items'] ?? [] as $it)
                                @php($key = $it['route'] ?? ($it['hub'] ?? ($it['name'] ?? '')))
                                <tr>
                                    <td class="muted mono" style="font-size:12px">└</td>
                                    <td>
                                        <span class="ico">{{ $it['icon'] ?? '' }}</span>
                                        {{ $it['name'] ?? '' }}
                                        @if (!empty($it['status']) && $it['status'] !== 'ready')
                                            <span class="badge badge-muted">规划中</span>
                                        @endif
                                        @if (!empty($it['permission']))
                                            <span class="muted" style="font-size:12px">（{{ $it['permission'] }}）</span>
                                        @endif
                                    </td>
                                    <td>
                                        <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
                                            <input type="checkbox" name="hidden_items[]" value="{{ $key }}"
                                                {{ in_array($key, $override['hidden_items'], true) ? 'checked' : '' }}>
                                            <span class="muted">隐藏</span>
                                        </label>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="hint" style="margin-top:10px">
                    说明：隐藏只影响侧边栏显示，已隐藏菜单的路由与权限校验仍然生效（防止「看不见但能进」的越权）；
                    序号越小越靠前，相同序号按原顺序排列。
                </div>
            </div>
            <div class="card-body" style="border-top:1px solid var(--line)">
                <button class="btn btn-primary" type="submit">保存配置</button>
            </div>
        </div>
    </form>

    <div class="card" style="margin-top:18px">
        <div class="card-head"><h2 class="card-title">自定义链接</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>名称</th><th>图标</th><th>链接</th><th>所属分组</th><th>操作</th></tr></thead>
                    <tbody>
                    @forelse ($override['custom'] as $i => $link)
                        <tr>
                            <td class="strong">{{ $link['name'] ?? '' }}</td>
                            <td>{{ $link['icon'] ?? '🔗' }}</td>
                            <td class="mono" style="max-width:280px;overflow:hidden;text-overflow:ellipsis">{{ $link['url'] ?? '' }}</td>
                            <td>{{ ($link['group'] ?? '') !== '' ? $link['group'] : '自定义' }}</td>
                            <td class="nowrap">
                                <form method="post" action="{{ route('menus.custom.remove', $i) }}"
                                      onsubmit="return confirm('确认删除自定义菜单「{{ $link['name'] ?? '' }}」？')" style="display:inline">@csrf
                                    <button class="btn btn-sm btn-danger" type="submit">删除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><div class="big">🔗</div>暂无自定义链接，可在下方添加</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-body" style="border-top:1px solid var(--line)">
            <form method="post" action="{{ route('menus.custom.store') }}" class="inline-form" style="flex-wrap:wrap;gap:8px">
                @csrf
                <input type="text" name="name" maxlength="40" required placeholder="菜单名称" style="width:150px">
                <input type="text" name="icon" maxlength="8" placeholder="图标（选填）" style="width:110px">
                <input type="url" name="url" maxlength="255" required placeholder="https://..." style="width:240px">
                <select name="group" style="width:130px">
                    <option value="">自定义（新分组）</option>
                    @foreach ($customGroups as $g)
                        <option value="{{ $g }}">{{ $g }}</option>
                    @endforeach
                </select>
                <button class="btn btn-primary btn-sm" type="submit">＋ 添加链接</button>
            </form>
        </div>
    </div>
@endsection
