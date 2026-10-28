@extends('layouts.app')

@section('title', '角色权限')
@section('desc', '细粒度角色与权限分配 · admin 等价超级角色自动放行')

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">角色列表</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>角色</th><th>标识</th><th>说明</th><th class="num">权限数</th><th class="num">分配人数</th><th>操作</th></tr></thead>
                    <tbody>
                    @foreach ($roles as $r)
                        <tr>
                            <td class="strong">
                                {{ $r->label }}
                                @if ($r->is_super)<span class="badge badge-success">超级</span>@endif
                                @if ($r->is_system)<span class="badge badge-muted">内置</span>@endif
                            </td>
                            <td class="mono muted">{{ $r->name }}</td>
                            <td class="muted">{{ $r->description ?: '—' }}</td>
                            <td class="num">{{ $r->is_super ? '全部' : $r->permissions_count }}</td>
                            <td class="num">{{ $r->users_count }}</td>
                            <td class="nowrap">
                                @unless ($r->is_system)
                                    <form method="post" action="{{ route('roles.destroy', $r) }}" style="display:inline" onsubmit="return confirm('确认删除角色 {{ $r->label }}？')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" type="submit">删除</button>
                                    </form>
                                @else
                                    <span class="muted">—</span>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">新建角色</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('roles.store') }}" class="inline-form" style="flex-wrap:wrap;gap:10px">
                @csrf
                <input type="text" name="name" placeholder="标识（如 warehouse）" pattern="[a-z][a-z0-9_]*" maxlength="40" style="width:180px" required>
                <input type="text" name="label" placeholder="显示名（如 仓管员）" maxlength="60" style="width:180px" required>
                <input type="text" name="description" placeholder="说明（选填）" maxlength="120" style="width:240px">
                <button class="btn btn-primary" type="submit">创建</button>
            </form>
        </div>
    </div>

    @foreach ($roles as $r)
        @if (! $r->is_super)
            <div class="card">
                <div class="card-head">
                    <h2 class="card-title">权限配置 · {{ $r->label }}</h2>
                    <span class="muted">标识 {{ $r->name }}</span>
                </div>
                <div class="card-body">
                    <form method="post" action="{{ route('roles.permissions', $r) }}">
                        @csrf
                        @foreach ($catalog as $group => $items)
                            <div style="margin-bottom:14px">
                                <div class="strong" style="margin-bottom:8px">{{ $group }}</div>
                                <div style="display:flex;flex-wrap:wrap;gap:10px 22px">
                                    @foreach ($items as $key => $label)
                                        <label style="display:flex;align-items:center;gap:6px;font-size:13px">
                                            <input type="checkbox" name="permissions[]" value="{{ $key }}"
                                                {{ $r->permissions()->where('key', $key)->exists() ? 'checked' : '' }}>
                                            <span>{{ $label }}</span>
                                            <span class="muted mono" style="font-size:11px">{{ $key }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        <button class="btn btn-primary" type="submit">保存「{{ $r->label }}」权限</button>
                    </form>
                </div>
            </div>
        @endif
    @endforeach

    <div class="card">
        <div class="card-head"><h2 class="card-title">用户角色分配</h2><span class="muted">管理员账号自动拥有全部权限，无需分配</span></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>账号</th><th>基础角色</th><th>已分配角色</th><th>分配角色</th></tr></thead>
                    <tbody>
                    @foreach ($users as $u)
                        <tr>
                            <td>
                                <div class="strong">{{ $u->name }}</div>
                                <div class="muted" style="font-size:12px">{{ $u->email }}</div>
                            </td>
                            <td><span class="badge badge-{{ $u->isAdmin() ? 'success' : 'muted' }}">{{ $u->roleLabel() }}</span></td>
                            <td>
                                @forelse ($u->roles as $r)
                                    <span class="badge badge-info">{{ $r->label }}</span>
                                @empty
                                    <span class="muted">—</span>
                                @endforelse
                            </td>
                            <td>
                                @if ($u->isAdmin())
                                    <span class="muted">超级角色</span>
                                @else
                                    <form method="post" action="{{ route('roles.assign') }}" class="inline-form">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $u->id }}">
                                        <select name="role_ids[]" multiple size="1" style="min-width:180px">
                                            @foreach ($roles->where('is_super', false) as $r)
                                                <option value="{{ $r->id }}" {{ $u->roles->contains($r->id) ? 'selected' : '' }}>{{ $r->label }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm" type="submit">保存</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
