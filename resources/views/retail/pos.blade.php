@extends('layouts.app')

@section('title', '门店开单')
@section('desc', 'POS 零售开单 · 会员折扣 · 多支付 · 开单即扣库存')

@section('actions')
    <a class="btn" href="{{ route('retail.sales') }}">零售单</a>
    <a class="btn" href="{{ route('retail.members') }}">会员</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">收银台</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('retail.pos.store') }}">
                @csrf
                <div class="form-grid">
                    <label class="field">
                        <span class="field-label">会员（可选）</span>
                        <select name="member_id" id="member-sel">
                            <option value="">散客</option>
                            @foreach ($members as $m)
                                <option value="{{ $m->id }}" data-discount="{{ $m->discount }}">{{ $m->name }}（{{ $m->discountText() }}）</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span class="field-label">支付方式</span>
                        <select name="payment_method">
                            <option value="cash">现金</option>
                            <option value="wechat">微信</option>
                            <option value="alipay">支付宝</option>
                            <option value="card">银行卡</option>
                            <option value="balance">储值余额</option>
                        </select>
                    </label>
                    <label class="field">
                        <span class="field-label">手动优惠（¥）</span>
                        <input type="number" name="discount" id="manual-discount" step="0.01" min="0" value="0" style="width:120px">
                    </label>
                    <label class="field">
                        <span class="field-label">备注</span>
                        <input type="text" name="remark" placeholder="可选">
                    </label>
                </div>

                <div class="card-head" style="margin-top:18px"><h3 class="card-title">商品明细</h3></div>
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
                    </table>
                </div>
                <button type="button" class="btn btn-sm" id="add-row">＋ 添加商品</button>

                <div class="card-head" style="margin-top:18px"></div>
                <div class="form-grid">
                    <div class="field"><span class="field-label">小计</span><div class="strong" id="sum-subtotal">¥0.00</div></div>
                    <div class="field"><span class="field-label">优惠</span><div class="strong down" id="sum-discount">¥0.00</div></div>
                    <div class="field"><span class="field-label">应收</span><div class="strong up" style="font-size:20px" id="sum-total">¥0.00</div></div>
                </div>

                <div style="margin-top:16px">
                    <button class="btn btn-primary" type="submit" onclick="return confirm('确认收银开单？将扣减库存。')">确认收银</button>
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
            var memberSel = document.getElementById('member-sel');
            var manual = document.getElementById('manual-discount');
            function memberRate() {
                var opt = memberSel.selectedOptions[0];
                var d = opt ? parseFloat(opt.getAttribute('data-discount') || 1) : 1;
                return (d > 0 && d <= 1) ? d : 1;
            }
            function recalc() {
                var subtotal = 0;
                body.querySelectorAll('tr').forEach(function (tr) {
                    var q = parseFloat(tr.querySelector('.qty').value) || 0;
                    var p = parseFloat(tr.querySelector('.price').value) || 0;
                    var st = q * p; subtotal += st;
                    tr.querySelector('.subtotal').textContent = '¥' + st.toFixed(2);
                });
                var memberDisc = subtotal * (1 - memberRate());
                var manualDisc = parseFloat(manual.value) || 0;
                var discount = memberDisc + manualDisc;
                var total = Math.max(0, subtotal - discount);
                document.getElementById('sum-subtotal').textContent = '¥' + subtotal.toFixed(2);
                document.getElementById('sum-discount').textContent = '−¥' + discount.toFixed(2);
                document.getElementById('sum-total').textContent = '¥' + total.toFixed(2);
            }
            function addRow() {
                var node = tpl.content.cloneNode(true);
                node.querySelector('.qty').addEventListener('input', recalc);
                node.querySelector('.price').addEventListener('input', recalc);
                body.appendChild(node);
                recalc();
            }
            document.getElementById('add-row').addEventListener('click', addRow);
            memberSel.addEventListener('change', recalc);
            manual.addEventListener('input', recalc);
            body.addEventListener('click', function (e) {
                if (e.target.classList.contains('row-del')) { e.target.closest('tr').remove(); recalc(); }
            });
            addRow();
        })();
    </script>
@endsection
