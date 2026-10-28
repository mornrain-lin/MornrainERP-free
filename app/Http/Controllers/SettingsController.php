<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 系统参数（仅管理员）
 *
 *  - company_name            公司名称（登录页 / 侧边栏品牌副标题）
 *  - monthly_order_quota     月度订单额度（覆盖 config/plan.php；0 = 不限量）
 *  - default_safety_stock    默认安全库存（商品未单独设置时的回退值）
 */
class SettingsController extends Controller
{
    public function index()
    {
        $values = [
            'company_name' => Setting::get('company_name', '港湾ERP'),
            'monthly_order_quota' => Setting::get('monthly_order_quota', config('plan.monthly_order_quota', 400)),
            'default_safety_stock' => Setting::get('default_safety_stock', 10),
        ];

        return view('settings.index', compact('values'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => 'required|string|max:60',
            'monthly_order_quota' => 'required|integer|min:0|max:1000000',
            'default_safety_stock' => 'required|integer|min:0|max:100000',
        ], [
            'company_name.required' => '请填写公司名称',
            'monthly_order_quota.required' => '请填写月度订单额度',
            'default_safety_stock.required' => '请填写默认安全库存',
        ]);

        Setting::set('company_name', $data['company_name']);
        Setting::set('monthly_order_quota', (string) $data['monthly_order_quota']);
        Setting::set('default_safety_stock', (string) $data['default_safety_stock']);

        app(AuditService::class)->log('system.config_updated', '更新系统参数');

        return back()->with('ok', '系统参数已保存');
    }
}
