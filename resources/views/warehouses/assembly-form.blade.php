@extends('layouts.app')

@section('title', '新建组装 / 拆卸单')
@section('desc', \App\Models\Assembly::typeLabel($type) . '：耗用一侧、产出另一侧，自动过账默认仓库存')

@section('actions')
    <a class="btn" href="{{ route('warehouses.assemblies.index') }}">返回列表</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">单据信息</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('warehouses.assemblies.store') }}">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="form-grid">
                    <label class="field">
                        <span class="field-label">类型</span>
                        <div class="strong">{{ \App\Models\Assembly::typeLabel($type) }}</div>
                    </label>
                    <label class="field">
                        <span class="field-label">成品 / 主件 <span class="req">*</span></span>
                        <select name="product_id" required>
                            <option value="">请选择</option>
                            @foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->sku }} · {{ $p->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field">
                        <span class="field-label">{{ $type === 'disassemble' ? '拆卸数量' : '组装数量' }} <span class="req">*</span></span>
                        <input type="number" name="qty" min="1" value="1" required style="width:120px">
                    </label>
                    <label class="field">
                        <span class="field-label">备注</span>
                        <input type="text" name="note" placeholder="可选">
                    </label>
                </div>

                <div class="card-head" style="margin-top:18px">
                    <h3 class="card-title">{{ $type === 'disassemble' ? '产出子件明细' : '耗用子件明细' }}</h3>
                </div>
                <div class="table-wrap">
                    <table class="tbl" id="line-table">
                        <thead>
                        <tr>
                            <th style="width:260px">商品</th>
                            <th class="num" style="width:160px">{{ $type === 'disassemble' ? '每个成品产出数量' : '每个成品耗用数量' }}</th>
                            <th style="width:60px"></th>
                        </tr>
                        </thead>
                        <tbody id="line-body"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm" id="add-row">＋ 添加明细行</button>

                <div style="margin-top:18px">
                    <button class="btn btn-primary" type="submit">创建单据</button>
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
            <td class="num"><input type="number" name="item_qty[]" min="1" value="1" required style="width:140px"></td>
            <td><button type="button" class="btn btn-sm btn-danger row-del">×</button></td>
        </tr>
    </template>

    <script>
        (function () {
            var body = document.getElementById('line-body');
            var tpl = document.getElementById('row-tpl');
            function addRow() { body.appendChild(tpl.content.cloneNode(true)); }
            document.getElementById('add-row').addEventListener('click', addRow);
            body.addEventListener('click', function (e) {
                if (e.target.classList.contains('row-del')) { e.target.closest('tr').remove(); }
            });
            addRow();
        })();
    </script>
@endsection
