@extends('layouts.app')

@section('title', '仓库与分仓')
@section('desc', '多仓库管理 · 分仓库存总览')

@section('actions')
    <a class="btn btn-primary" href="{{ route('warehouses.transfers.index') }}">仓库调拨</a>
    <a class="btn" href="{{ route('warehouses.assemblies.index') }}">组装拆卸</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">仓库列表</h2>
            <div class="card-desc">新建 / 设置默认 / 删除</div>
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('warehouses.store') }}" class="inline-form" style="margin-bottom:14px">
                @csrf
                <input type="text" name="name" placeholder="仓库名称" required style="width:160px">
                <input type="text" name="location" placeholder="位置" style="width:160px">
                <button class="btn btn-sm btn-primary" type="submit">＋ 新建仓库</button>
            </form>
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>名称</th><th>位置</th><th>默认</th><th class="num">SKU 数</th><th>操作</th></tr></thead>
                    <tbody>
                    @forelse ($warehouses as $wh)
                        <tr>
                            <td>{{ $wh->name }}{{ $wh->is_default ? ' ⭐' : '' }}</td>
                            <td class="muted">{{ $wh->location ?? '—' }}</td>
                            <td>{!! $wh->is_default ? '<span class="badge badge-success">默认</span>' : '—' !!}</td>
                            <td class="num">{{ $wh->stocks->count() }}</td>
                            <td class="nowrap">
                                @if (! $wh->is_default)
                                    <form method="post" action="{{ route('warehouses.default', $wh) }}" class="inline-form">
                                        @csrf
                                        <button class="btn btn-sm" type="submit">设默认</button>
                                    </form>
                                @endif
                                <form method="post" action="{{ route('warehouses.destroy', $wh) }}" class="inline-form" onsubmit="return confirm('确认删除该仓库？')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" type="submit">删除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">还没有仓库，先新建一个</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-2" style="margin-top:18px">
        @foreach ($warehouses as $wh)
            <div class="card">
                <div class="card-head"><h2 class="card-title">{{ $wh->name }}</h2><div class="card-desc">{{ $wh->location ?? '' }}</div></div>
                <div class="card-body tight">
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead><tr><th>SKU</th><th>名称</th><th class="num">库存</th></tr></thead>
                            <tbody>
                            @forelse ($wh->stocks as $s)
                                <tr>
                                    <td class="mono">{{ $s->product?->sku }}</td>
                                    <td>{{ $s->product?->name ?? '—' }}</td>
                                    <td class="num">{{ $s->stock }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="empty">暂无分仓库存</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
