@extends('layouts.app')

@section('title', '客户管理')
@section('desc', 'B 端客户档案 · 联系人 · 等级 · 成交概览')

@section('actions')
    <a class="btn" href="{{ route('sales.orders') }}">销售订单</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">客户列表</h2><div class="card-desc">CRM 雏形：档案 + 成交订单数</div></div>
        <div class="card-body">
            <form method="post" action="{{ route('sales.customers.store') }}" class="inline-form" style="flex-wrap:wrap;gap:10px;margin-bottom:14px">
                @csrf
                <input type="text" name="name" placeholder="客户名称" required style="width:180px">
                <input type="text" name="contact" placeholder="联系人" style="width:110px">
                <input type="text" name="phone" placeholder="电话" style="width:130px">
                <input type="text" name="email" placeholder="邮箱" style="width:170px">
                <select name="level" style="width:96px">
                    <option value="normal">普通</option>
                    <option value="vip">VIP</option>
                    <option value="gold">金牌</option>
                </select>
                <button class="btn btn-sm btn-primary" type="submit">＋ 新建客户</button>
            </form>
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>名称</th><th>联系人</th><th>电话</th><th>邮箱</th><th>等级</th><th class="num">订单数</th><th>状态</th><th>操作</th></tr></thead>
                    <tbody>
                    @forelse ($customers as $c)
                        <tr>
                            <td class="strong">{{ $c->name }}<div class="muted" style="font-size:11px">{{ $c->address }}</div></td>
                            <td class="muted">{{ $c->contact ?? '—' }}</td>
                            <td class="muted">{{ $c->phone ?? '—' }}</td>
                            <td class="muted">{{ $c->email ?? '—' }}</td>
                            <td><span class="badge {{ $c->level === 'gold' ? 'badge-warn' : ($c->level === 'vip' ? 'badge-info' : 'badge-muted') }}">{{ \App\Models\Customer::levelLabel($c->level) }}</span></td>
                            <td class="num">{{ $c->sale_orders_count }}</td>
                            <td>
                                @if ($c->is_active)
                                    <span class="badge badge-success">启用</span>
                                @else
                                    <span class="badge badge-muted">停用</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                <form method="post" action="{{ route('sales.customers.toggle', $c) }}" class="inline-form">
                                    @csrf
                                    <button class="btn btn-sm" type="submit">{{ $c->is_active ? '停用' : '启用' }}</button>
                                </form>
                                <form method="post" action="{{ route('sales.customers.destroy', $c) }}" class="inline-form" onsubmit="return confirm('确认删除该客户？')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" type="submit">删除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty">还没有客户，先在上面新建一个</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
