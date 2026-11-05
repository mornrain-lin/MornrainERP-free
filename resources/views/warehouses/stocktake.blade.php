@extends('layouts.app')

@section('title', '库存盘点')
@section('desc', '录入各 SKU 实盘数量，差异自动过账到所选仓库')

@section('actions')
    <a class="btn" href="{{ route('warehouses.index') }}">仓库总览</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">盘点单</h2>
            <span class="muted" style="font-size:12px">差异 = 实盘 − 账面，仅对存在差异的 SKU 过账</span>
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('warehouses.stocktake.store') }}">
                @csrf
                <label class="field" style="min-width:240px">
                    <span class="field-label">盘点仓库 <span class="req">*</span></span>
                    <select name="warehouse_id" required>
                        <option value="">请选择</option>
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}" {{ $defaultWh && $wh->id === $defaultWh->id ? 'selected' : '' }}>{{ $wh->name }}{{ $wh->is_default ? '（默认）' : '' }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="table-wrap" style="margin-top:14px">
                    <table class="tbl">
                        <thead>
                        <tr><th>SKU</th><th>商品</th><th class="num">账面库存</th><th class="num">实盘数量</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($products as $p)
                            <tr>
                                <td class="mono">{{ $p->sku }}</td>
                                <td>{{ $p->name }}</td>
                                <td class="num muted">{{ $p->stock }}</td>
                                <td class="num">
                                    <input type="hidden" name="rows[{{ $loop->index }}][product_id]" value="{{ $p->id }}">
                                    <input type="number" name="rows[{{ $loop->index }}][actual]" min="0" value="{{ $p->stock }}" style="width:110px">
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:16px">
                    <button class="btn btn-primary" type="submit"
                            onclick="return confirm('确认提交盘点？差异将自动过账到所选仓库。')">提交盘点并过账差异</button>
                </div>
            </form>
        </div>
    </div>
@endsection
