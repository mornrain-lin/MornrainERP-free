@extends('layouts.app')

@section('title', '组织架构')
@section('desc', '多组织核算 · 三级结构（集团 → 公司 → 部门）')

@section('content')
<div class="card">
    <div class="card-head">
        <h2 class="card-title">组织树</h2>
        <div class="card-actions">
            <a class="btn btn-primary btn-sm" href="{{ route('organizations.create') }}">+ 新建顶级组织</a>
            <form method="POST" action="{{ route('organizations.rebuild') }}" style="display:inline">
                @csrf
                <button class="btn btn-sm" type="submit" onclick="return confirm('确认重建全部路径？')">重建路径</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        @if ($nodes->isEmpty())
            <p class="muted">暂无组织，请先创建。</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>名称</th>
                        <th>类型</th>
                        <th>编码</th>
                        <th>层级</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($nodes as $n)
                        <tr>
                            <td style="padding-left:{{ $n->level * 22 }}px">
                                @if ($n->level > 0)<span class="muted">└</span>@endif
                                <strong>{{ $n->name }}</strong>
                                @if ($n->code === 'ROOT')<span class="badge badge-muted">系统根</span>@endif
                            </td>
                            <td>{{ App\Models\Organization::TYPES[$n->type] ?? $n->type }}</td>
                            <td class="muted">{{ $n->code ?: '—' }}</td>
                            <td>{{ $n->level }}</td>
                            <td>
                                @if ($n->status)
                                    <span class="badge badge-ok">启用</span>
                                @else
                                    <span class="badge badge-muted">停用</span>
                                @endif
                            </td>
                            <td class="row-actions">
                                <a class="btn btn-sm" href="{{ route('organizations.create', ['parent_id' => $n->id]) }}">加下级</a>
                                <a class="btn btn-sm" href="{{ route('organizations.edit', $n) }}">编辑</a>
                                @if ($n->code !== 'ROOT')
                                    <form method="POST" action="{{ route('organizations.destroy', $n) }}" style="display:inline"
                                          onsubmit="return confirm('确认删除「{{ $n->name }}」？')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" type="submit">删除</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
