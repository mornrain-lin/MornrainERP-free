@extends('layouts.app')

@section('title', $org ? '编辑组织' : '新建组织')
@section('desc', '三级结构：集团 → 公司 → 部门')

@section('content')
<div class="card">
    <div class="card-head">
        <h2 class="card-title">{{ $org ? '编辑：'.$org->name : '新建组织' }}</h2>
    </div>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-err">
                @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form method="POST"
              action="{{ $org ? route('organizations.update', $org) : route('organizations.store') }}"
              class="form-grid">
            @csrf
            @if ($org) @method('PUT') @endif

            <div class="field">
                <label>名称 <span class="req">*</span></label>
                <input type="text" name="name" value="{{ old('name', $org->name ?? '') }}" required maxlength="60">
            </div>

            <div class="field">
                <label>类型 <span class="req">*</span></label>
                <select name="type" required>
                    @foreach (App\Models\Organization::TYPES as $t => $label)
                        <option value="{{ $t }}" @selected(old('type', $type ?? '') === $t)>{{ $label }}</option>
                    @endforeach
                </select>
                @if ($parent)
                    <small class="muted">上级：{{ $parent->name }}（{{ App\Models\Organization::TYPES[$parent->type] }}）</small>
                @endif
            </div>

            <div class="field">
                <label>上级组织</label>
                <select name="parent_id">
                    <option value="">（无）</option>
                    @foreach ($candidates as $c)
                        <option value="{{ $c->id }}" @selected(old('parent_id', $org->parent_id ?? $parent?->id ?? '') == $c->id)>
                            {{ str_repeat('— ', $c->level) }}[{{ App\Models\Organization::TYPES[$c->type] }}] {{ $c->name }}
                        </option>
                    @endforeach
                </select>
                <small class="muted">集团无上级；公司上级为集团；部门上级为公司。</small>
            </div>

            <div class="field">
                <label>编码</label>
                <input type="text" name="code" value="{{ old('code', $org->code ?? '') }}" maxlength="40"
                       placeholder="选填，唯一" autocomplete="off">
            </div>

            <div class="field">
                <label>排序</label>
                <input type="number" name="sort" value="{{ old('sort', $org->sort ?? 0) }}">
            </div>

            <div class="field">
                <label>状态</label>
                <select name="status">
                    <option value="1" @selected(old('status', $org->status ?? 1) == 1)>启用</option>
                    <option value="0" @selected(old('status', $org->status ?? 1) == 0)>停用</option>
                </select>
            </div>

            <div class="field" style="grid-column:1/-1">
                <button class="btn btn-primary" type="submit">保存</button>
                <a class="btn" href="{{ route('organizations.index') }}">返回</a>
            </div>
        </form>
    </div>
</div>
@endsection
