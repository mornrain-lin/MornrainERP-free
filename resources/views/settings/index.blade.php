@extends('layouts.app')

@section('title', '系统参数')
@section('desc', '公司信息 / 额度策略 / 库存预警默认值')

@section('content')
    <div class="card">
        <div class="card-head"><h2 class="card-title">基础参数</h2></div>
        <div class="card-body">
            <form method="post" action="{{ route('settings.update') }}">
                @csrf
                @method('PUT')
                <div class="form-grid">
                    <div class="field">
                        <label class="req">公司名称</label>
                        <input type="text" name="company_name" maxlength="60" value="{{ $values['company_name'] }}" required>
                        <span class="muted" style="font-size:12px">显示在侧边栏品牌副标题</span>
                    </div>
                    <div class="field">
                        <label class="req">月度订单额度</label>
                        <input type="number" name="monthly_order_quota" min="0" max="1000000" value="{{ $values['monthly_order_quota'] }}" required>
                        <span class="muted" style="font-size:12px">免费版默认 400 单/月；0 = 不限量</span>
                    </div>
                    <div class="field">
                        <label class="req">默认安全库存</label>
                        <input type="number" name="default_safety_stock" min="0" max="100000" value="{{ $values['default_safety_stock'] }}" required>
                        <span class="muted" style="font-size:12px">商品未单独设置安全库存时的回退值，用于低库存预警</span>
                    </div>
                </div>
                <div style="margin-top:16px">
                    <button class="btn btn-primary" type="submit">保存参数</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2 class="card-title">说明</h2></div>
        <div class="card-body">
            <ul class="muted" style="font-size:13px;line-height:1.9;padding-left:18px">
                <li>「月度订单额度」即时生效：影响新建 / 导入 / 拉单三处额度校验与概览页额度条。</li>
                <li>「默认安全库存」用于商品未单独设置 safety_stock 时的低库存预警与采购建议回退。</li>
                <li>「公司名称」显示在侧边栏品牌副标题；金额口径、仓库、菜单等参数见对应模块页面。</li>
            </ul>
        </div>
    </div>
@endsection
