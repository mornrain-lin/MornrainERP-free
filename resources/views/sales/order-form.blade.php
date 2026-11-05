@extends('layouts.app')

@section('title', '新建销售订单')
@section('desc', '选择客户与商品，录入数量与售价（人民币）')

@section('actions')
    <a class="btn" href="{{ route('sales.orders') }}">返回列表</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">订单信息</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('sales.orders.store') }}">
                @csrf
                <div class="form-grid">
                    <label class="field">
                        <span class="field-label">客户</span>
                        <select name="customer_id">
                            <option value="">散客（不选）</option>
                            @foreach ($customers as $c)<option value="{{ $c->id }}" {{ isset($presetCustomer) && $presetCustomer->id === $c->id ? 'selected' : '' }}>{{ $c->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span class="field-label">下单日期</span>
                        <input type="date" name="order_date" value="{{ date('Y-m-d') }}">
                    </label>
                    <label class="field">
                        <span class="field-label">预计交货</span>
                        <input type="date" name="expected_at">
                    </label>
                    <label class="field">
                        <span class="field-label">备注</span>
                        <input type="text" name="remark" placeholder="可选">
                    </label>
                </div>

                <div class="card-head" style="margin-top:18px"><h3 class="card-title">销售明细</h3></div>
                <div class="table-wrap">
                    <table class="tbl" id="line-table">
                        <thead>
                        <tr>
                            <th style="width:280px">商品</th>
                            <th class="num" style="width:110px">数量</th>
                            <th class="num" style="width:130px">单价（¥）</th>
                            <th class="num" style="width:120px">小计</th>
                            <th style="width:56px"></th>
                        </tr>
                        </thead>
                        <tbody id="line-body"></tbody>
                        <tfoot>
                        <tr><td colspan="3" class="num strong">合计</td><td class="num strong up" id="grand-total">¥0.00</td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
                <button type="button" class="btn btn-sm" id="add-row">＋ 添加明细行</button>

                <div style="margin-top:18px">
                    <button class="btn btn-primary" type="submit">创建销售订单</button>
                </div>
            </form>
        </div>
    </div>

    <template id="row-tpl">
        <tr>
            <td>
                <select name="item_product_id[]" required>
                    <option value="">请选择商品</option>
                    @foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->sku }} · {{ $p->name }}</option>@endforeach
                </select>
            </td>
            <td class="num"><input type="number" name="item_qty[]" class="qty" min="1" value="1" required style="width:90px"></td>
            <td class="num"><input type="number" name="item_unit_price[]" class="price" step="0.01" min="0" value="0" required style="width:110px"></td>
            <td class="num strong subtotal">¥0.00</td>
            <td><button type="button" class="btn btn-sm btn-danger row-del">×</button></td>
        </tr>
    </template>

    <script>
        (function () {
            var body = document.getElementById('line-body');
            var tpl = document.getElementById('row-tpl');
            var grand = document.getElementById('grand-total');
            function recalc() {
                var total = 0;
                body.querySelectorAll('tr').forEach(function (tr) {
                    var q = parseFloat(tr.querySelector('.qty').value) || 0;
                    var p = parseFloat(tr.querySelector('.price').value) || 0;
                    var st = q * p; total += st;
                    tr.querySelector('.subtotal').textContent = '¥' + st.toFixed(2);
                });
                grand.textContent = '¥' + total.toFixed(2);
            }
            function addRow() {
                var node = tpl.content.cloneNode(true);
                node.querySelector('.qty').addEventListener('input', recalc);
                node.querySelector('.price').addEventListener('input', recalc);
                body.appendChild(node);
                recalc();
            }
            document.getElementById('add-row').addEventListener('click', addRow);
            body.addEventListener('click', function (e) {
                if (e.target.classList.contains('row-del')) { e.target.closest('tr').remove(); recalc(); }
            });
            addRow();
        })();
    </script>
@endsection
