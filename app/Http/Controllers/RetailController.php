<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RetailMember;
use App\Models\RetailReturn;
use App\Models\RetailSale;
use App\Services\Retail\RetailService;
use Illuminate\Http\Request;

class RetailController extends Controller
{
    public function __construct(private RetailService $retail) {}

    /** 会员管理 */
    public function members()
    {
        $members = RetailMember::withCount('sales')->orderBy('id')->get();

        return view('retail.members', compact('members'));
    }

    public function memberStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:60',
            'phone' => 'nullable|string|max:40|unique:retail_members,phone',
            'level' => 'nullable|in:normal,silver,gold',
            'discount' => 'nullable|numeric|min:0.1|max:1',
            'remark' => 'nullable|string|max:255',
        ]);
        RetailMember::create([
            'card_no' => $this->nextCardNo(),
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'level' => $data['level'] ?? 'normal',
            'discount' => $data['discount'] ?? 1.00,
            'balance' => 0,
            'points' => 0,
            'remark' => $data['remark'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('retail.members')->with('ok', '会员已创建');
    }

    public function memberTopUp(Request $request, RetailMember $member)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0.01']);
        $this->retail->topUp($member, (float) $data['amount']);

        return back()->with('ok', '充值成功，当前余额 ¥' . number_format((float) $member->fresh()->balance, 2));
    }

    public function memberToggle(RetailMember $member)
    {
        $member->update(['is_active' => ! $member->is_active]);

        return back()->with('ok', $member->is_active ? '会员已启用' : '会员已停用');
    }

    /** 门店开单（POS） */
    public function pos()
    {
        $members = RetailMember::where('is_active', true)->orderBy('name')->get();
        $products = Product::orderBy('sku')->get();

        return view('retail.pos', compact('members', 'products'));
    }

    public function posStore(Request $request)
    {
        $data = $request->validate([
            'member_id' => 'nullable|exists:retail_members,id',
            'payment_method' => 'required|in:cash,wechat,alipay,card,balance',
            'discount' => 'nullable|numeric|min:0',
            'remark' => 'nullable|string|max:255',
        ]);
        $items = $this->collectLineItems($request);
        if (empty($items)) {
            return back()->with('err', '请至少添加一项商品')->withInput();
        }

        $sale = $this->retail->createSale(
            $data['member_id'] ?? null, $items, (float) ($data['discount'] ?? 0),
            $data['payment_method'], $data['remark'] ?? null, $request->user()->id,
        );

        return redirect()->route('retail.sales.show', $sale)->with('ok', "开单成功 {$sale->sale_no}，应收 ¥" . number_format($sale->total, 2));
    }

    /** 零售单列表 */
    public function sales(Request $request)
    {
        $status = $request->query('status');
        $query = RetailSale::with('member')->withSum('items', 'quantity')->latest();
        $valid = [RetailSale::STATUS_COMPLETED, RetailSale::STATUS_REFUNDED, RetailSale::STATUS_CANCELED];
        if (in_array($status, $valid, true)) {
            $query->where('status', $status);
        }
        $list = $query->paginate(20)->withQueryString();

        return view('retail.sales', compact('list', 'status'));
    }

    public function saleShow(RetailSale $sale)
    {
        $sale->load('items.product', 'member', 'cashier', 'returns');

        return view('retail.sale-show', compact('sale'));
    }

    public function saleRefund(Request $request, RetailSale $sale)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:255']);
        $return = $this->retail->createReturn($sale, $data['reason'] ?? null, $request->user()->id);

        return back()->with('ok', "已退货 {$return->return_no}，退回 ¥" . number_format($return->total, 2) . '，库存已回补');
    }

    /** 退货列表 */
    public function returns()
    {
        $list = RetailReturn::with('sale.member', 'handler')->latest()->paginate(20);

        return view('retail.returns', compact('list'));
    }

    private function collectLineItems(Request $request): array
    {
        $ids = $request->input('item_product_id', []);
        $qtys = $request->input('item_qty', []);
        $prices = $request->input('item_unit_price', []);

        $out = [];
        foreach ($ids as $i => $pid) {
            $pid = (int) $pid;
            $qty = (int) ($qtys[$i] ?? 0);
            $price = (float) ($prices[$i] ?? 0);
            if ($pid <= 0 || $qty <= 0 || $price < 0) {
                continue;
            }
            $out[] = ['product_id' => $pid, 'qty' => $qty, 'unit_price' => $price];
        }

        return $out;
    }

    private function nextCardNo(): string
    {
        return 'M' . date('Ymd') . str_pad(RetailMember::whereDate('created_at', now())->count() + 1, 3, '0', STR_PAD_LEFT);
    }
}
