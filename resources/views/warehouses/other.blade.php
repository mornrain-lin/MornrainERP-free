@extends('layouts.app')

@section('title', '其他出入库')
@section('desc', '登记非订单、非调拨的库存变动（报损 / 报溢 / 赠送 / 样品等）')

@section('actions')
    <a class="btn" href="{{ route('warehouses.index') }}">仓库总览</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">登记一笔其他出入库</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('warehouses.other.store') }}" class="inline-form" style="flex-wrap:wrap;gap:12px">
                @csrf
                <label class="field" style="min-width:240px">
                    <span class="field-label">商品 <span class="req">*</span></span>
                    <select name="product_id" required>
                        <option value="">请选择</option>
                        @foreach ($products as $p)<option value="{{ $p->id }}">{{ $p->sku }} · {{ $p->name }}</option>@endforeach
                    </select>
                </label>
                <label class="field" style="min-width:200px">
                    <span class="field-label">仓库 <span class="req">*</span></span>
                    <select name="warehouse_id" required>
                        <option value="">请选择</option>
                        @foreach ($warehouses as $wh)<option value="{{ $wh->id }}">{{ $wh->name }}</option>@endforeach
                    </select>
                </label>
                <label class="field" style="min-width:160px">
                    <span class="field-label">方向 <span class="req">*</span></span>
                    <select name="direction" required>
                        <option value="in">入库 +</option>
                        <option value="out">出库 −</option>
                    </select>
                </label>
                <label class="field" style="min-width:120px">
                    <span class="field-label">数量 <span class="req">*</span></span>
                    <input type="number" name="qty" min="1" value="1" required style="width:100px">
                </label>
                <label class="field" style="min-width:200px">
                    <span class="field-label">备注</span>
                    <input type="text" name="remark" placeholder="如：报损 / 样品">
                </label>
                <div class="field" style="align-self:flex-end">
                    <button class="btn btn-primary" type="submit">登记</button>
                </div>
            </form>
            <div class="muted" style="font-size:12px;margin-top:10px">
                该操作会同步更新商品总库存与该仓库的分仓库存，并写入流水（类型「其他」）。
            </div>
        </div>
    </div>
@endsection
