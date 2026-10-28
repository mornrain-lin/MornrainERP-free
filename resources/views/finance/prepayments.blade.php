@extends('layouts.app')

@section('title', '预付款管理')
@section('desc', '预付供应商款 / 预收客户款，支持核销与取消退回')

@section('actions')
    <a class="btn" href="{{ route('finance.index') }}">财务总览</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">登记预付 / 预收</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('finance.prepayments.store') }}" class="inline-form" style="flex-wrap:wrap;gap:10px">
                @csrf
                <select name="direction" style="width:120px">
                    <option value="out">预付供应商</option>
                    <option value="in">预收客户</option>
                </select>
                <input type="text" name="party_name" placeholder="供应商 / 客户名称" required style="width:200px">
                <select name="account_id" style="width:150px" required>
                    <option value="">资金账户</option>
                    @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                </select>
                <input type="number" name="amount" step="0.01" min="0.01" placeholder="金额" style="width:110px" required>
                <input type="date" name="occurred_on" value="{{ date('Y-m-d') }}" style="width:150px">
                <input type="text" name="remark" placeholder="备注" style="width:160px">
                <button class="btn btn-primary" type="submit">登记</button>
            </form>
            <div class="muted" style="font-size:12px;margin-top:8px">
                登记即发生资金流转（预付=账户支出，预收=账户收入）；「核销」消耗额度但不再动余额，「取消」会把资金退回账户。
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">预付 / 预收列表</h2></div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>单号</th><th>类型</th><th>对方</th><th class="num">金额</th><th class="num">已核销</th>
                        <th class="num">未核销</th><th>状态</th><th>账户</th><th class="nowrap">日期</th><th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($list as $p)
                        <tr>
                            <td class="mono">{{ $p->prepay_no }}</td>
                            <td>
                                <span class="badge {{ $p->direction === 'out' ? 'badge-info' : 'badge-success' }}">
                                    {{ \App\Models\FinancePrepayment::directionLabel($p->direction) }}
                                </span>
                            </td>
                            <td>{{ $p->party_name }}</td>
                            <td class="num">¥{{ number_format($p->amount, 2) }}</td>
                            <td class="num muted">¥{{ number_format($p->used_amount, 2) }}</td>
                            <td class="num strong {{ $p->remaining > 0 ? 'up' : 'muted' }}">¥{{ number_format($p->remaining, 2) }}</td>
                            <td><span class="badge badge-{{ $p->statusTone($p->status) }}">{{ $p->statusLabel($p->status) }}</span></td>
                            <td class="muted">{{ $p->account?->name ?? '—' }}</td>
                            <td class="nowrap mono">{{ $p->occurred_on?->format('Y-m-d') }}</td>
                            <td class="nowrap">
                                @if (in_array($p->status, [\App\Models\FinancePrepayment::STATUS_PENDING, \App\Models\FinancePrepayment::STATUS_PARTIAL]))
                                    <form method="post" action="{{ route('finance.prepayments.apply', $p) }}" class="inline-form">
                                        @csrf
                                        <input type="number" name="amount" step="0.01" min="0.01" max="{{ $p->remaining }}" placeholder="核销额" style="width:88px" required>
                                        <button class="btn btn-sm" type="submit">核销</button>
                                    </form>
                                @endif
                                @if ($p->status === \App\Models\FinancePrepayment::STATUS_PENDING)
                                    <form method="post" action="{{ route('finance.prepayments.cancel', $p) }}" class="inline-form" onsubmit="return confirm('确认取消？资金将退回账户。')">
                                        @csrf
                                        <button class="btn btn-sm btn-danger" type="submit">取消</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="empty"><div class="big">💰</div>暂无预付款 / 预收款</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($list->hasPages())
            <div class="pagination-wrap">{{ $list->links() }}</div>
        @endif
    </div>
@endsection
