<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrgIntercompanyInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 内部存货交易（未实现利润）登记。
 *
 * 这些是合并报表「内部未实现利润」抵消的数据来源。合并引擎只读取、
 * 不写入这些记录；登记与删除在本控制器完成。仅作合并口径的辅助录入，
 * 不产生任何记账流水。
 */
class InventoryUnrealizedController extends Controller
{
    public function index(Request $request): View
    {
        $orgs = Organization::where('status', 1)
            ->orderBy('level')->orderBy('sort')->orderBy('id')->get();
        $entries = OrgIntercompanyInventory::orderByDesc('period_to')
            ->orderByDesc('id')->get();

        return view('consolidation.inventory', compact('orgs', 'entries'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'seller_org_id' => ['required', 'exists:organizations,id'],
            'buyer_org_id' => ['required', 'exists:organizations,id', 'different:seller_org_id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'cost' => ['required', 'numeric', 'min:0'],
            'unsold_ratio' => ['required', 'numeric', 'min:0', 'max:1'],
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $profit = round((float) $data['amount'] - (float) $data['cost'], 2);
        $unrealized = round($profit * (float) $data['unsold_ratio'], 2);

        OrgIntercompanyInventory::create([
            'period_from' => $data['period_from'],
            'period_to' => $data['period_to'],
            'seller_org_id' => (int) $data['seller_org_id'],
            'buyer_org_id' => (int) $data['buyer_org_id'],
            'amount' => (float) $data['amount'],
            'cost' => (float) $data['cost'],
            'profit' => $profit,
            'unsold_ratio' => (float) $data['unsold_ratio'],
            'unrealized_profit' => $unrealized,
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('consolidation.inventory')
            ->with('ok', '已登记内部存货交易（未实现利润 ¥'.number_format($unrealized, 2).'）');
    }

    public function destroy(OrgIntercompanyInventory $inventory): RedirectResponse
    {
        $inventory->delete();

        return redirect()->route('consolidation.inventory')
            ->with('ok', '已删除该未实现利润登记');
    }
}
