<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FinanceAccount;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleQuote;
use App\Models\SaleReturn;
use App\Services\Sales\SalesService;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function __construct(private SalesService $sales) {}

    /** 客户管理 */
    public function customers()
    {
        $customers = Customer::withCount('saleOrders')->orderBy('id')->get();

        return view('sales.customers', compact('customers'));
    }

    public function customerStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:96',
            'contact' => 'nullable|string|max:60',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:96',
            'address' => 'nullable|string|max:200',
            'level' => 'nullable|in:normal,vip,gold',
            'remark' => 'nullable|string|max:255',
        ]);
        Customer::create($data + ['is_active' => true]);

        return redirect()->route('sales.customers')->with('ok', '客户已创建');
    }

    public function customerToggle(Customer $customer)
    {
        $customer->update(['is_active' => ! $customer->is_active]);

        return back()->with('ok', $customer->is_active ? '客户已启用' : '客户已停用');
    }

    public function customerDestroy(Customer $customer)
    {
        if ($customer->saleOrders()->exists()) {
            return back()->with('err', '该客户已有销售订单，无法删除');
        }
        $customer->delete();

        return back()->with('ok', '客户已删除');
    }

    /** 销售订单列表 */
    public function orders(Request $request)
    {
        $status = $request->query('status');
        $query = SaleOrder::with('customer')->withSum('items', 'quantity')->latest();
        $valid = [SaleOrder::STATUS_DRAFT, SaleOrder::STATUS_CONFIRMED, SaleOrder::STATUS_SHIPPED, SaleOrder::STATUS_COMPLETED, SaleOrder::STATUS_CANCELED];
        if (in_array($status, $valid, true)) {
            $query->where('status', $status);
        }
        $list = $query->paginate(20)->withQueryString();
        $customers = Customer::orderBy('name')->get();

        return view('sales.orders', compact('list', 'customers', 'status'));
    }

    public function orderCreate(Request $request)
    {
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $products = Product::orderBy('sku')->get();
        // CRM 商机赢单转入：预填客户
        $presetCustomer = $request->query('customer_id')
            ? Customer::find($request->query('customer_id'))
            : null;

        return view('sales.order-form', compact('customers', 'products', 'presetCustomer'));
    }

    public function orderStore(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'order_date' => 'nullable|date',
            'expected_at' => 'nullable|date',
            'remark' => 'nullable|string|max:255',
        ]);
        $items = $this->collectLineItems($request);
        if (empty($items)) {
            return back()->with('err', '请至少添加一项销售明细')->withInput();
        }

        $order = $this->sales->createOrder(
            $data['customer_id'] ?? null, $items, $data['order_date'] ?? null,
            $data['expected_at'] ?? null, $data['remark'] ?? null, $request->user()->id,
        );

        return redirect()->route('sales.orders.show', $order)->with('ok', '销售订单已创建');
    }

    public function orderShow(SaleOrder $order)
    {
        $order->load('items.product', 'customer', 'creator');

        return view('sales.order-show', compact('order'));
    }

    public function orderConfirm(SaleOrder $order)
    {
        $this->sales->confirmOrder($order);

        return back()->with('ok', '订单已确认');
    }

    public function orderShip(SaleOrder $order)
    {
        $moved = $this->sales->shipOrder($order, auth()->id());

        return back()->with('ok', $moved > 0 ? "已发货，扣减 {$moved} 个 SKU 库存" : '订单状态不符，未发货');
    }

    public function orderComplete(SaleOrder $order)
    {
        $this->sales->completeOrder($order);

        return back()->with('ok', '订单已完成');
    }

    public function orderCancel(SaleOrder $order)
    {
        $this->sales->cancelOrder($order);

        return back()->with('ok', '订单已取消');
    }

    public function orderPay(Request $request, SaleOrder $order)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0.01']);
        $this->sales->registerPayment($order, (float) $data['amount']);

        return back()->with('ok', '已登记回款 ¥' . number_format(min($data['amount'], $order->remaining), 2));
    }

    // ---------------------------------------------------------------- 销售退货

    /** 退货单列表 */
    public function returns(Request $request)
    {
        $status = $request->query('status');
        $query = SaleReturn::with('order', 'customer')->latest();
        $valid = [SaleReturn::STATUS_PENDING, SaleReturn::STATUS_STOCKED, SaleReturn::STATUS_REFUNDED, SaleReturn::STATUS_CANCELED];
        if (in_array($status, $valid, true)) {
            $query->where('status', $status);
        }
        $list = $query->paginate(20)->withQueryString();

        return view('sales.returns', compact('list', 'status'));
    }

    /** 选择要退货的销售订单 */
    public function returnSelect()
    {
        $orders = SaleOrder::with('customer')
            ->whereIn('status', [SaleOrder::STATUS_SHIPPED, SaleOrder::STATUS_COMPLETED])
            ->latest()
            ->limit(50)
            ->get();

        return view('sales.return-select', compact('orders'));
    }

    /** 退货登记表单（按订单明细行填数量） */
    public function returnCreate(SaleOrder $order)
    {
        $order->load('items', 'customer');

        return view('sales.return-form', compact('order'));
    }

    public function returnStore(Request $request, SaleOrder $order)
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:120',
            'return_date' => 'nullable|date',
            'remark' => 'nullable|string|max:255',
        ]);
        $lines = [];
        foreach ((array) $request->input('qty', []) as $itemId => $qty) {
            $qty = (int) $qty;
            if ($qty > 0) {
                $lines[] = ['sale_order_item_id' => (int) $itemId, 'qty' => $qty];
            }
        }
        if (empty($lines)) {
            return back()->with('err', '请至少填写一项退货数量')->withInput();
        }

        $return = $this->sales->createReturn(
            $order, $lines, $data['reason'] ?? null, $data['return_date'] ?? null,
            $data['remark'] ?? null, $request->user()->id,
        );

        return redirect()->route('sales.returns.show', $return)->with('ok', "退货单 {$return->return_no} 已创建");
    }

    public function returnShow(SaleReturn $return)
    {
        $return->load('items', 'order.customer', 'customer', 'creator');
        $accounts = FinanceAccount::where('is_active', true)->orderBy('id')->get();

        return view('sales.return-show', compact('return', 'accounts'));
    }

    public function returnStock(SaleReturn $return)
    {
        if ($return->status !== SaleReturn::STATUS_PENDING) {
            return back()->with('err', '仅待入库的退货单可执行入库');
        }
        $this->sales->stockReturn($return, auth()->id());

        return back()->with('ok', '退货已入库，库存已回补');
    }

    public function returnRefund(Request $request, SaleReturn $return)
    {
        $data = $request->validate([
            'account_id' => 'required|exists:finance_accounts,id',
            'amount' => 'required|numeric|min:0.01',
        ]);
        $account = FinanceAccount::findOrFail($data['account_id']);
        $this->sales->refundReturn($return, $account, (float) $data['amount'], auth()->id());

        return back()->with('ok', '已退款 ¥' . number_format((float) $data['amount'], 2) . '，流水已入账');
    }

    public function returnCancel(SaleReturn $return)
    {
        if ($return->status !== SaleReturn::STATUS_PENDING) {
            return back()->with('err', '仅待入库的退货单可取消');
        }
        $this->sales->cancelReturn($return);

        return back()->with('ok', '退货单已取消');
    }

    /** 报价单列表 */
    public function quotes(Request $request)
    {
        $status = $request->query('status');
        $query = SaleQuote::with('customer')->latest();
        $valid = [SaleQuote::STATUS_DRAFT, SaleQuote::STATUS_SENT, SaleQuote::STATUS_ACCEPTED, SaleQuote::STATUS_REJECTED, SaleQuote::STATUS_CONVERTED, SaleQuote::STATUS_EXPIRED];
        if (in_array($status, $valid, true)) {
            $query->where('status', $status);
        }
        $list = $query->paginate(20)->withQueryString();

        return view('sales.quotes', compact('list', 'status'));
    }

    public function quoteCreate()
    {
        $customers = Customer::where('is_active', true)->orderBy('name')->get();
        $products = Product::orderBy('sku')->get();

        return view('sales.quote-form', compact('customers', 'products'));
    }

    public function quoteStore(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'valid_until' => 'nullable|date',
            'remark' => 'nullable|string|max:255',
        ]);
        $items = $this->collectLineItems($request);
        if (empty($items)) {
            return back()->with('err', '请至少添加一项报价明细')->withInput();
        }

        $quote = $this->sales->createQuote(
            $data['customer_id'] ?? null, $items, $data['valid_until'] ?? null, $data['remark'] ?? null, $request->user()->id,
        );

        return redirect()->route('sales.quotes.show', $quote)->with('ok', '报价单已创建');
    }

    public function quoteShow(SaleQuote $quote)
    {
        $quote->load('customer', 'creator');

        return view('sales.quote-show', compact('quote'));
    }

    public function quoteStatus(Request $request, SaleQuote $quote)
    {
        $data = $request->validate(['status' => 'required|in:draft,sent,accepted,rejected,expired']);
        $this->sales->quoteStatus($quote, $data['status']);

        return back()->with('ok', '报价单状态已更新');
    }

    public function quoteConvert(SaleQuote $quote)
    {
        $order = $this->sales->convertQuote($quote, auth()->id());
        if (! $order) {
            return back()->with('err', '该报价单无法转换（无明细或已转换）');
        }

        return redirect()->route('sales.orders.show', $order)->with('ok', "已由报价单生成销售订单 {$order->so_no}");
    }

    /** 收集明细行：product_id / qty / unit_price 成对 */
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
}
