@extends('layouts.app')

@section('title', '新建调拨单')
@section('desc', '选择调出仓与调入仓，录入需要转移的 SKU 与数量')

@section('actions')
    <a class="btn" href="{{ route('warehouses.transfers.index') }}">返回列表</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">调拨单信息</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('warehouses.transfers.store') }}">
                @csrf
                <div class="form-grid">
                    <label class="field">
                        <span class="field-label">调出仓库 <span class="req">*</span></span>
                        <select name="from_warehouse_id" required>
                            <option value="">请选择</option>
                            @foreach ($warehouses as $wh)<option value="{{ $wh->id }}">{{ $wh->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span class="field-label">调入仓库 <span class="req">*</span></span>
                        <select name="to_warehouse_id" required>
                            <option value="">请选择</option>
                            @foreach ($warehouses as $wh)<option value="{{ $wh->id }}">{{ $wh->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span class="field-label">备注</span>
                        <input type="text" name="note" placeholder="可选，如调拨原因">
                    </label>
                </div>

                <div class="card-head" style="margin-top:18px"><h3 class="card-title">调拨明细</h3></div>
                <div class="table-wrap">
                    <table class="tbl" id="line-table">
                        <thead>
                        <tr>
                            <th style="width:260px">商品</th><th class="num" style="width:140px">数量</th><th style="width:60px"></th>
                        </tr>
                        </thead>
                        <tbody id="line-body"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm" id="add-row">＋ 添加明细行</button>

                <div style="margin-top:18px">
                    <button class="btn btn-primary" type="submit">创建调拨单</button>
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
            <td class="num"><input type="number" name="item_qty[]" min="1" value="1" required style="width:120px"></td>
            <td><button type="button" class="btn btn-sm btn-danger row-del">×</button></td>
        </tr>
    </template>

    <script>
        (function () {
            var body = document.getElementById('line-body');
            var tpl = document.getElementById('row-tpl');
            function addRow() {
                var node = tpl.content.cloneNode(true);
                body.appendChild(node);
            }
            document.getElementById('add-row').addEventListener('click', addRow);
            body.addEventListener('click', function (e) {
                if (e.target.classList.contains('row-del')) {
                    e.target.closest('tr').remove();
                }
            });
            addRow();
        })();
    </script>
@endsection
