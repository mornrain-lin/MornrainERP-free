@extends('layouts.app')

@section('title', '收支流水')
@section('desc', '登记每一笔收入与支出，账户余额自动同步')

@section('actions')
    <a class="btn" href="{{ route('finance.index') }}">财务总览</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">记一笔流水</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('finance.flows.store') }}" class="inline-form" style="flex-wrap:wrap;gap:10px">
                @csrf
                <select name="direction" id="fl-direction" style="width:96px">
                    <option value="expense">支出</option>
                    <option value="income">收入</option>
                </select>
                <select name="category" id="fl-category" style="width:130px"></select>
                <select name="account_id" style="width:150px" required>
                    <option value="">选择账户</option>
                    @foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach
                </select>
                <input type="number" name="amount" step="0.01" min="0.01" placeholder="金额" style="width:110px" required>
                <input type="date" name="occurred_on" value="{{ date('Y-m-d') }}" style="width:150px">
                <input type="text" name="remark" placeholder="备注" style="width:180px">
                <button class="btn btn-primary" type="submit">登记</button>
            </form>
            <div class="muted" style="font-size:12px;margin-top:8px">预付款产生的流水请在「预付款管理」登记，此处用于日常收入 / 支出台账。</div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">流水列表</h2>
            <form method="get" action="{{ route('finance.flows') }}" class="inline-form">
                <select name="direction" style="width:96px">
                    <option value="">全部</option>
                    <option value="income" {{ $direction === 'income' ? 'selected' : '' }}>收入</option>
                    <option value="expense" {{ $direction === 'expense' ? 'selected' : '' }}>支出</option>
                </select>
                <select name="account_id" style="width:150px">
                    <option value="">全部账户</option>
                    @foreach ($accounts as $a)
                        <option value="{{ $a->id }}" {{ (string) $accountId === (string) $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm" type="submit">筛选</button>
            </form>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr><th class="nowrap">日期</th><th>单号</th><th>方向</th><th>分类</th><th>账户</th><th class="num">金额</th><th>操作人</th><th>备注</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse ($list as $f)
                        <tr>
                            <td class="nowrap mono">{{ $f->occurred_on?->format('Y-m-d') }}</td>
                            <td class="mono">{{ $f->flow_no }}</td>
                            <td>
                                <span class="badge {{ $f->direction === 'income' ? 'badge-success' : 'badge-info' }}">
                                    {{ \App\Models\FinanceFlow::directionLabel($f->direction) }}
                                </span>
                            </td>
                            <td>{{ \App\Models\FinanceFlow::categoryLabel($f->category) }}</td>
                            <td class="muted">{{ $f->account?->name ?? '—' }}</td>
                            <td class="num strong {{ $f->direction === 'income' ? 'up' : 'down' }}">
                                {{ $f->direction === 'income' ? '+' : '−' }}¥{{ number_format($f->amount, 2) }}
                            </td>
                            <td class="muted">{{ $f->creator?->name ?? '—' }}</td>
                            <td class="muted" style="font-size:12px">{{ \Illuminate\Support\Str::limit($f->remark, 24) ?: '—' }}</td>
                            <td class="nowrap">
                                @if ($f->category !== \App\Models\FinanceFlow::CAT_PREPAY)
                                    <form method="post" action="{{ route('finance.flows.destroy', $f) }}" class="inline-form" onsubmit="return confirm('删除后账户余额会相应冲销，确认？')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-danger" type="submit">删除</button>
                                    </form>
                                @else
                                    <span class="muted" style="font-size:11px">预付</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="empty"><div class="big">📊</div>暂无流水</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($list->hasPages())
            <div class="pagination-wrap">{{ $list->links() }}</div>
        @endif
    </div>

    <script>
        (function () {
            var cats = {
                income: {sales: '销售收入', refund_in: '退款收回', other_in: '其他收入'},
                expense: {purchase: '采购支出', shipping: '物流费用', ad: '广告费用', commission: '平台佣金', fee: '支付手续费', refund_out: '退款支出', other_out: '其他支出'}
            };
            var dirSel = document.getElementById('fl-direction');
            var catSel = document.getElementById('fl-category');
            function render() {
                var map = cats[dirSel.value] || cats.expense;
                catSel.innerHTML = '';
                for (var k in map) {
                    var o = document.createElement('option');
                    o.value = k; o.textContent = map[k];
                    catSel.appendChild(o);
                }
            }
            dirSel.addEventListener('change', render);
            render();
        })();
    </script>
@endsection
