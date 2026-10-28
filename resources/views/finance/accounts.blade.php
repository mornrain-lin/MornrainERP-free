@extends('layouts.app')

@section('title', '资金账户')
@section('desc', '现金 / 银行 / 支付宝 / 微信等资金账户与余额')

@section('actions')
    <a class="btn" href="{{ route('finance.index') }}">财务总览</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">账户列表</h2><div class="card-desc">余额随收支流水自动更新</div></div>
        <div class="card-body">
            <form method="post" action="{{ route('finance.accounts.store') }}" class="inline-form" style="flex-wrap:wrap;gap:10px;margin-bottom:14px">
                @csrf
                <input type="text" name="name" placeholder="账户名称，如：工商银行基本户" required style="width:240px">
                <select name="type" style="width:120px">
                    <option value="cash">现金</option>
                    <option value="bank" selected>银行</option>
                    <option value="alipay">支付宝</option>
                    <option value="wechat">微信</option>
                    <option value="other">其他</option>
                </select>
                <button class="btn btn-sm btn-primary" type="submit">＋ 新建账户</button>
            </form>
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>名称</th><th>类型</th><th class="num">余额</th><th class="num">流水数</th><th>状态</th><th>操作</th></tr></thead>
                    <tbody>
                    @forelse ($accounts as $a)
                        <tr>
                            <td class="strong">{{ $a->name }}<div class="muted" style="font-size:11px">{{ $a->note }}</div></td>
                            <td class="muted">{{ \App\Models\FinanceAccount::typeLabel($a->type) }}</td>
                            <td class="num strong {{ $a->balance < 0 ? 'down' : '' }}">¥{{ number_format($a->balance, 2) }}</td>
                            <td class="num">{{ $a->flows_count }}</td>
                            <td>
                                @if ($a->is_active)
                                    <span class="badge badge-success">启用</span>
                                @else
                                    <span class="badge badge-muted">停用</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                <form method="post" action="{{ route('finance.accounts.toggle', $a) }}" class="inline-form">
                                    @csrf
                                    <button class="btn btn-sm" type="submit">{{ $a->is_active ? '停用' : '启用' }}</button>
                                </form>
                                <form method="post" action="{{ route('finance.accounts.destroy', $a) }}" class="inline-form" onsubmit="return confirm('确认删除该账户？')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-danger" type="submit">删除</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">还没有账户，先在上面新建一个</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
