@extends('layouts.app')

@section('title', '退货登记 · ' . $order->so_no)
@section('desc', '按原销售明细填写退货数量，金额按原售价快照计算')

@section('actions')
    <a class="btn" href="{{ route('sales.orders.show', $order) }}">返回订单</a>
@endsection

@section('content')
    <form method="post" action="{{ route('sales.returns.store', $order) }}" id="returnForm">
        @csrf
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">退货信息</h2>
                <span class="badge badge-{{ $order->statusTone($order->status) }}">{{ $order->statusLabel($order->status) }}</span>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="field">
                        <span class="field-label">原销售单</span>
                        <div class="strong mono">{{ $order->so_no }}</div>
                    </div>
                    <div class="field">
                        <span class="field-label">客户</span>
                        <div>{{ $order->customer?->name ?? '散客' }}</div>
                    </div>
                    <div class="field">
                        <label class="req">退货日期</label>
                        <input type="date" name="return_date" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="field">
                        <label>退货原因</label>
                        <input type="text" name="reason" maxlength="120" placeholder="如：质量问题 / 发错货 / 客户拒收">
                    </div>
                    <div class="field" style="grid-column: 1 / -1">
                        <label>备注</label>
                        <input type="text" name="remark" maxlength="255" placeholder="选填">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2 class="card-title">退货明细</h2></div>
            <div class="card-body tight">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                        <tr><th>SKU</th><th>商品</th><th class="num">可退数量</th><th class="num">原售价</th><th style="width:150px">退货数量</th><th class="num">小计</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($order->items as $it)
                            <tr>
                                <td class="mono">{{ $it->sku }}</td>
                                <td>{{ $it->product_name }}</td>
                                <td class="num">{{ $it->quantity }}</td>
                                <td class="num">¥{{ number_format($it->unit_price, 2) }}</td>
                                <td>
                                    <input type="number" name="qty[{{ $it->id }}]" min="0" max="{{ $it->quantity }}" step="1" value="0"
                                           class="qty-input" data-price="{{ $it->unit_price }}" style="width:100%">
                                </td>
                                <td class="num strong line-total">¥0.00</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">该订单无明细</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                        <tr>
                            <td colspan="5" class="num strong">退货金额合计</td>
                            <td class="num strong up" id="grandTotal">¥0.00</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body" style="display:flex;gap:10px;align-items:center">
                <button class="btn btn-primary" type="submit" onclick="return confirm('提交退货单？')">提交退货单</button>
                <a class="btn" href="{{ route('sales.orders.show', $order) }}">取消</a>
                <span class="muted" style="font-size:12px">提交后进入「待入库」，货回仓后办理入库与退款</span>
            </div>
        </div>
    </form>

    <script>
        (function () {
            var form = document.getElementById('returnForm');
            if (!form) return;
            var inputs = form.querySelectorAll('.qty-input');
            var totalEl = document.getElementById('grandTotal');
            function fmt(n) { return '¥' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
            function recalc() {
                var grand = 0;
                inputs.forEach(function (inp) {
                    var qty = parseInt(inp.value || '0', 10) || 0;
                    var price = parseFloat(inp.getAttribute('data-price') || '0') || 0;
                    var line = qty * price;
                    grand += line;
                    var cell = inp.closest('tr').querySelector('.line-total');
                    if (cell) cell.textContent = fmt(line);
                });
                totalEl.textContent = fmt(grand);
            }
            inputs.forEach(function (inp) { inp.addEventListener('input', recalc); });
        })();
    </script>
@endsection
