@extends('layouts.app')

@php($editing = $req->exists)

@section('title', $editing ? '编辑请购单 · ' . $req->requisition_no : '新建请购单')
@section('desc', $editing
    ? '仅「草稿 / 已驳回」状态可编辑；提交审批后内容锁定'
    : '登记部门请购需求与明细，保存为草稿后可提交审批')

@section('actions')
    <a class="btn" href="{{ route('requisitions.index') }}">← 返回列表</a>
@endsection

@section('content')
<form method="post" action="{{ $editing ? route('requisitions.update', $req) : route('requisitions.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card">
        <div class="card-head"><h2 class="card-title">基础信息</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="field">
                    <label>申请部门 *</label>
                    <input type="text" name="department" maxlength="60" required
                           value="{{ old('department', $req->department) }}" placeholder="如：运营部 / 采购部">
                </div>
                <div class="field">
                    <label>优先级</label>
                    <select name="priority">
                        <option value="normal" @selected(old('priority', $req->priority ?: 'normal') === 'normal')>普通</option>
                        <option value="urgent" @selected(old('priority', $req->priority) === 'urgent')>加急</option>
                    </select>
                </div>
                <div class="field">
                    <label>期望到货日期</label>
                    <input type="date" name="expected_date"
                           value="{{ old('expected_date', $editing && $req->expected_date ? $req->expected_date->toDateString() : '') }}">
                </div>
            </div>
            <div class="field" style="margin-top:14px">
                <label>备注</label>
                <textarea name="remark" rows="2" maxlength="500"
                          placeholder="请购事由、用途等（选填）">{{ old('remark', $req->remark) }}</textarea>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <div class="card-head">
            <h2 class="card-title">请购明细</h2>
            <button class="btn btn-sm" type="button" onclick="addRow()">＋ 添加一行</button>
        </div>
        <div class="card-body">
            <div class="table-wrap">
                <table class="tbl" id="items">
                    <thead>
                    <tr>
                        <th style="min-width:150px">SKU</th>
                        <th style="min-width:170px">商品名称</th>
                        <th style="width:90px">数量</th>
                        <th style="width:130px">预估单价(CNY)</th>
                        <th style="width:130px">行小计</th>
                        <th style="width:50px"></th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="hint" style="margin-top:10px">
                预估总额：<strong id="grand-total">¥0.00</strong>（预估单价默认取商品库成本价，可手工调整）
            </div>
        </div>
    </div>

    <div class="btn-row" style="margin-top:18px">
        <button class="btn btn-primary" type="submit">{{ $editing ? '保存修改' : '保存请购单' }}</button>
        <a class="btn" href="{{ route('requisitions.index') }}">取消</a>
    </div>
</form>

<script>
    const PRODUCTS = @json($products->map(fn ($p) => ['sku' => $p->sku, 'name' => $p->name, 'cost' => (float) $p->cost_price]));
    const INITIAL = @json($items);
    const tbody = document.querySelector('#items tbody');

    function productOptions(selected) {
        return PRODUCTS.map(p =>
            `<option value="${p.sku}" data-name="${p.name}" data-cost="${p.cost}" ${selected === p.sku ? 'selected' : ''}>${p.sku}</option>`
        ).join('');
    }

    function addRow(item) {
        item = item || {};
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><select name="items[][sku]" onchange="syncProduct(this)" style="width:100%">
                <option value="">— 选择 SKU —</option>${productOptions(item.sku || '')}
            </select></td>
            <td><input type="text" name="items[][product_name]" style="width:100%"></td>
            <td><input type="number" min="1" name="items[][quantity]" class="qty" oninput="recalc(this)" style="width:100%"></td>
            <td><input type="number" step="0.01" min="0" name="items[][unit_cost]" class="cost" oninput="recalc(this)" style="width:100%"></td>
            <td class="num line-total">¥0.00</td>
            <td><button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove(); grandTotal()">×</button></td>
        `;
        tbody.appendChild(tr);

        tr.querySelector('[name="items[][product_name]"]').value = item.product_name || '';
        tr.querySelector('.qty').value = item.quantity ?? 1;
        tr.querySelector('.cost').value = item.unit_cost ?? 0;
        recalc(tr.querySelector('.cost'));
    }

    function syncProduct(sel) {
        const opt = sel.selectedOptions[0];
        if (!opt || !opt.value) return;
        const tr = sel.closest('tr');
        tr.querySelector('[name="items[][product_name]"]').value = opt.dataset.name || '';
        const cost = tr.querySelector('.cost');
        if (!cost.value) cost.value = opt.dataset.cost || 0;
        recalc(cost);
    }

    function recalc(el) {
        const tr = el.closest('tr');
        const q = parseFloat(tr.querySelector('.qty')?.value || 0);
        const c = parseFloat(tr.querySelector('.cost')?.value || 0);
        tr.querySelector('.line-total').textContent = '¥' + (q * c).toFixed(2);
        grandTotal();
    }

    function grandTotal() {
        let sum = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const t = tr.querySelector('.line-total')?.textContent || '0';
            sum += parseFloat(t.replace(/[^\d.-]/g, '')) || 0;
        });
        document.getElementById('grand-total').textContent = '¥' + sum.toFixed(2);
    }

    if (INITIAL.length) {
        INITIAL.forEach(addRow);
    } else {
        addRow();
    }
</script>
@endsection
