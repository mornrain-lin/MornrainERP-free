@extends('layouts.app')

@section('title', '会员管理')
@section('desc', '会员档案 · 等级折扣 · 积分 · 储值余额')

@section('actions')
    <a class="btn btn-primary" href="{{ route('retail.pos') }}">🛒 门店开单</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">会员列表</h2><div class="card-desc">消费 ¥1 = 1 积分；储值可用于支付</div></div>
        <div class="card-body">
            <form method="post" action="{{ route('retail.members.store') }}" class="inline-form" style="flex-wrap:wrap;gap:10px;margin-bottom:14px">
                @csrf
                <input type="text" name="name" placeholder="会员姓名" required style="width:150px">
                <input type="text" name="phone" placeholder="手机号" style="width:140px">
                <select name="level" style="width:96px">
                    <option value="normal">普通</option>
                    <option value="silver">银卡</option>
                    <option value="gold">金卡</option>
                </select>
                <input type="number" name="discount" step="0.01" min="0.1" max="1" placeholder="折扣 0.95" style="width:110px" title="折扣率，如 0.95 = 95 折，1 = 无折扣">
                <button class="btn btn-sm btn-primary" type="submit">＋ 新建会员</button>
            </form>
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>卡号</th><th>姓名</th><th>手机</th><th>等级</th><th>折扣</th><th class="num">积分</th><th class="num">储值余额</th><th class="num">订单数</th><th>状态</th><th>操作</th></tr></thead>
                    <tbody>
                    @forelse ($members as $m)
                        <tr>
                            <td class="mono">{{ $m->card_no }}</td>
                            <td class="strong">{{ $m->name }}</td>
                            <td class="muted">{{ $m->phone ?? '—' }}</td>
                            <td><span class="badge {{ $m->level === 'gold' ? 'badge-warn' : ($m->level === 'silver' ? 'badge-info' : 'badge-muted') }}">{{ \App\Models\RetailMember::levelLabel($m->level) }}</span></td>
                            <td class="muted">{{ $m->discountText() }}</td>
                            <td class="num">{{ number_format($m->points) }}</td>
                            <td class="num strong">¥{{ number_format($m->balance, 2) }}</td>
                            <td class="num">{{ $m->sales_count }}</td>
                            <td>
                                @if ($m->is_active)
                                    <span class="badge badge-success">启用</span>
                                @else
                                    <span class="badge badge-muted">停用</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                <form method="post" action="{{ route('retail.members.topup', $m) }}" class="inline-form">
                                    @csrf
                                    <input type="number" name="amount" step="0.01" min="0.01" placeholder="充值额" style="width:88px" required>
                                    <button class="btn btn-sm" type="submit">充值</button>
                                </form>
                                <form method="post" action="{{ route('retail.members.toggle', $m) }}" class="inline-form">
                                    @csrf
                                    <button class="btn btn-sm" type="submit">{{ $m->is_active ? '停用' : '启用' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="empty">还没有会员，先在上面新建一个</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
