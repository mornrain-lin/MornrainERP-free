@extends('layouts.app')

@section('title', '内部往来')
@section('desc', '组织间资金划拨登记 · 合并报表抵消依据')

@section('actions')
    <a class="btn" href="{{ route('consolidation.index') }}">查看合并报表</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">登记内部往来</h2>
            <span class="muted small">自动生成付款方支出 / 收款方收入两条镜像流水</span>
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('intercompany.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="field">
                        <label>付款方组织</label>
                        <select name="from_organization_id" required>
                            <option value="">请选择</option>
                            @foreach ($orgs as $org)
                                <option value="{{ $org->id }}" @selected(old('from_organization_id') == $org->id)>{{ str_repeat('— ', $org->level) }}{{ $org->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>收款方组织</label>
                        <select name="to_organization_id" required>
                            <option value="">请选择</option>
                            @foreach ($orgs as $org)
                                <option value="{{ $org->id }}" @selected(old('to_organization_id') == $org->id)>{{ str_repeat('— ', $org->level) }}{{ $org->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>付款账户（须属付款方组织）</label>
                        <select name="from_account_id" required>
                            <option value="">请选择</option>
                            @foreach ($orgs as $org)
                                @if (($accounts[$org->id] ?? null)?->isNotEmpty())
                                    <optgroup label="{{ $org->name }}">
                                        @foreach ($accounts[$org->id] as $a)
                                            <option value="{{ $a->id }}" @selected(old('from_account_id') == $a->id)>{{ $a->name }} · ¥{{ number_format((float) $a->balance, 2) }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>收款账户（须属收款方组织）</label>
                        <select name="to_account_id" required>
                            <option value="">请选择</option>
                            @foreach ($orgs as $org)
                                @if (($accounts[$org->id] ?? null)?->isNotEmpty())
                                    <optgroup label="{{ $org->name }}">
                                        @foreach ($accounts[$org->id] as $a)
                                            <option value="{{ $a->id }}" @selected(old('to_account_id') == $a->id)>{{ $a->name }} · ¥{{ number_format((float) $a->balance, 2) }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>金额（CNY）</label>
                        <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required>
                    </div>
                    <div class="field">
                        <label>发生日期</label>
                        <input type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}">
                    </div>
                    <div class="field">
                        <label>往来类型</label>
                        <select name="category" required>
                            @foreach ($categories as $key => $label)
                                <option value="{{ $key }}" @selected(old('category', 'transfer') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>备注</label>
                        <input type="text" name="remark" maxlength="255" value="{{ old('remark') }}" placeholder="如：总部代垫货款 / 月度管理费分摊">
                    </div>
                </div>
                <div class="btn-row">
                    <button class="btn btn-primary" type="submit">登记并生成镜像流水</button>
                    <span class="muted small">付款方余额减少、收款方余额增加；两侧独立账簿各自平衡</span>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2 class="card-title">内部往来台账</h2>
            <span class="muted small">已作废的记录保留作审计，但不再参与合并抵消</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr><th>单号</th><th>发生日</th><th>付款方 → 收款方</th><th>类型</th><th class="num">金额</th><th>状态</th><th>操作</th></tr>
                    </thead>
                    <tbody>
                    @forelse ($list as $txn)
                        <tr>
                            <td class="mono">{{ $txn->txn_no }}</td>
                            <td class="muted">{{ $txn->occurred_on?->format('Y-m-d') }}</td>
                            <td class="strong">{{ $txn->fromOrganization?->name ?? '—' }} → {{ $txn->toOrganization?->name ?? '—' }}</td>
                            <td class="muted">{{ \App\Models\OrgIntercompanyTransaction::categoryLabel($txn->category) }}</td>
                            <td class="num strong">¥{{ number_format((float) $txn->amount, 2) }}</td>
                            <td>
                                @if ($txn->isConfirmed())
                                    <span class="badge badge-success">已确认</span>
                                @else
                                    <span class="badge badge-muted">已作废</span>
                                @endif
                            </td>
                            <td class="nowrap">
                                @if ($txn->isConfirmed())
                                    <form method="post" action="{{ route('intercompany.cancel', $txn) }}" onsubmit="return confirm('作废将红冲两条镜像流水并回滚账户余额，确认继续？')">
                                        @csrf
                                        <button class="btn btn-sm btn-danger" type="submit">作废红冲</button>
                                    </form>
                                @else
                                    <span class="muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="empty"><div class="big">⇄</div>暂无内部往来登记</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $list->links() }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">抵消口径</h2></div>
        <div class="card-body small muted" style="line-height:1.9">
            <div>· 每笔登记生成两条等额反向流水，分别带上各自组织标记与同一个内部往来编号。</div>
            <div>· 合并报表仅在<strong>两端都在合并范围内</strong>时成对剔除这两条流水；只覆盖一端时保留，视同对外往来。</div>
            <div>· 手工录入的收支流水不会被自动抵消——抵消<strong>只以本页登记为准</strong>，避免误把真实外部交易抵掉。</div>
            <div>· 内部往来镜像流水不能在「收支流水」页单独删除，须在本页作废，以保证配对完整。</div>
            <div>· 作废为红冲操作：反向冲销两条流水对应的账户余额后删除流水，登记置为已作废并保留审计记录。</div>
        </div>
    </div>
@endsection
