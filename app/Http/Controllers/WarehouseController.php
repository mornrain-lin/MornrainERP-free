<?php

namespace App\Http\Controllers;

use App\Models\Assembly;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\Inventory\StockService;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(private StockService $stock) {}

    /** 仓库与分仓库存总览 + 新建仓库 */
    public function index()
    {
        $warehouses = Warehouse::with('stocks.product')->orderBy('id')->get();
        $products = Product::orderBy('sku')->get();

        return view('warehouses.index', compact('warehouses', 'products'));
    }

    public function storeWarehouse(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:60',
            'location' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:255',
        ]);
        Warehouse::create($data + ['is_default' => false]);

        return redirect()->route('warehouses.index')->with('ok', '仓库已创建');
    }

    public function setDefault(Request $request, Warehouse $warehouse)
    {
        Warehouse::where('is_default', true)->update(['is_default' => false]);
        $warehouse->update(['is_default' => true]);

        return back()->with('ok', '已设为默认仓库');
    }

    public function destroyWarehouse(Warehouse $warehouse)
    {
        if ($warehouse->stocks()->where('stock', '!=', 0)->exists()) {
            return back()->with('err', '该仓库仍有库存，无法删除');
        }
        $warehouse->delete();

        return back()->with('ok', '仓库已删除');
    }

    /** 调拨单列表 */
    public function transfers()
    {
        $list = StockTransfer::with('fromWarehouse', 'toWarehouse', 'creator')
            ->latest()->paginate(20);
        $warehouses = Warehouse::orderBy('id')->get();
        $products = Product::orderBy('sku')->get();

        return view('warehouses.transfers', compact('list', 'warehouses', 'products'));
    }

    public function transferCreate()
    {
        $warehouses = Warehouse::orderBy('id')->get();
        $products = Product::orderBy('sku')->get();

        return view('warehouses.transfer-form', compact('warehouses', 'products'));
    }

    public function transferStore(Request $request)
    {
        $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'note' => 'nullable|string|max:255',
        ]);

        $items = $this->collectLineItems($request);
        if (empty($items)) {
            return back()->with('err', '请至少添加一项调拨明细')->withInput();
        }

        $transfer = StockTransfer::create([
            'transfer_no' => 'TF' . date('Ymd') . str_pad(StockTransfer::count() + 1, 3, '0', STR_PAD_LEFT),
            'from_warehouse_id' => $request->from_warehouse_id,
            'to_warehouse_id' => $request->to_warehouse_id,
            'status' => StockTransfer::STATUS_PENDING,
            'note' => $request->note,
            'items' => $items,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('warehouses.transfers.show', $transfer)->with('ok', '调拨单已创建，可在详情页执行调拨');
    }

    public function transferShow(StockTransfer $transfer)
    {
        $transfer->load('fromWarehouse', 'toWarehouse', 'creator');

        return view('warehouses.transfer-show', compact('transfer'));
    }

    public function transferComplete(StockTransfer $transfer)
    {
        abort_if($transfer->status === StockTransfer::STATUS_DONE, 400, '已完成');
        $this->stock->transfer($transfer);

        return back()->with('ok', '调拨已执行：调出仓扣减、调入仓增加');
    }

    public function transferCancel(StockTransfer $transfer)
    {
        abort_if($transfer->status === StockTransfer::STATUS_DONE, 400, '已完成不可取消');
        $transfer->update(['status' => StockTransfer::STATUS_CANCELED]);

        return back()->with('ok', '调拨单已取消');
    }

    /** 组装 / 拆卸列表 */
    public function assemblies()
    {
        $list = Assembly::with('product', 'creator')->latest()->paginate(20);
        $products = Product::orderBy('sku')->get();

        return view('warehouses.assemblies', compact('list', 'products'));
    }

    public function assemblyCreate(Request $request)
    {
        $type = $request->query('type', Assembly::TYPE_ASSEMBLE);
        $products = Product::orderBy('sku')->get();

        return view('warehouses.assembly-form', compact('products', 'type'));
    }

    public function assemblyStore(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:assemble,disassemble',
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        $items = $this->collectLineItems($request);
        if (empty($items)) {
            return back()->with('err', '请至少添加一个子件 / 产出明细')->withInput();
        }

        $assembly = Assembly::create([
            'doc_no' => 'AS' . date('Ymd') . str_pad(Assembly::count() + 1, 3, '0', STR_PAD_LEFT),
            'type' => $data['type'],
            'product_id' => $data['product_id'],
            'qty' => $data['qty'],
            'note' => $data['note'] ?? null,
            'items' => $items,
            'status' => Assembly::STATUS_PENDING,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('warehouses.assemblies.show', $assembly)->with('ok', '单据已创建，可在详情页执行');
    }

    public function assemblyShow(Assembly $assembly)
    {
        $assembly->load('product', 'creator');

        return view('warehouses.assembly-show', compact('assembly'));
    }

    public function assemblyComplete(Assembly $assembly)
    {
        abort_if($assembly->status === Assembly::STATUS_DONE, 400, '已完成');
        $this->stock->assemble($assembly);

        return back()->with('ok', $assembly->type === Assembly::TYPE_DISASSEMBLE ? '拆卸已执行' : '组装已执行');
    }

    public function assemblyCancel(Assembly $assembly)
    {
        abort_if($assembly->status === Assembly::STATUS_DONE, 400, '已完成不可取消');
        $assembly->update(['status' => Assembly::STATUS_CANCELED]);

        return back()->with('ok', '单据已取消');
    }

    /** 其他出入库 */
    public function otherForm()
    {
        $products = Product::orderBy('sku')->get();
        $warehouses = Warehouse::orderBy('id')->get();

        return view('warehouses.other', compact('products', 'warehouses'));
    }

    public function otherStore(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'qty' => 'required|integer|not_in:0',
            'direction' => 'required|in:in,out',
            'remark' => 'nullable|string|max:255',
        ]);

        $product = Product::find($data['product_id']);
        $qty = $data['direction'] === 'out' ? -abs($data['qty']) : abs($data['qty']);
        $remark = $data['remark'] ?? null;
        $this->stock->adjustWarehouse(
            $product, $qty, StockMovement::TYPE_OTHER, $data['warehouse_id'],
            $remark ?: ('其他' . ($data['direction'] === 'out' ? '出库' : '入库')), $request->user()->id,
        );

        return redirect()->route('warehouses.other.form')->with('ok', '已登记其他出入库');
    }

    /** 盘点：录入实盘数量，差异自动过账 */
    public function stocktakeForm()
    {
        $warehouses = Warehouse::orderBy('id')->get();
        $products = Product::orderBy('sku')->get();
        $defaultWh = Warehouse::default()->first() ?? Warehouse::orderBy('id')->first();

        return view('warehouses.stocktake', compact('warehouses', 'products', 'defaultWh'));
    }

    public function stocktakeStore(Request $request)
    {
        $data = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $rows = $request->input('rows', []);
        $done = 0;
        foreach ($rows as $row) {
            $product = Product::find($row['product_id'] ?? null);
            if (! $product) {
                continue;
            }
            $actual = (int) ($row['actual'] ?? null);
            $diff = $actual - (int) $product->stock;
            if ($diff === 0) {
                continue;
            }
            $this->stock->adjustWarehouse(
                $product, $diff, StockMovement::TYPE_ADJUST, $data['warehouse_id'],
                '盘点调整', $request->user()->id,
            );
            $done++;
        }

        return redirect()->route('warehouses.stocktake.form')->with('ok', "盘点完成，已对 {$done} 个 SKU 过账差异");
    }

    /** 从表单收集明细行：product_id / qty 成对 */
    private function collectLineItems(Request $request): array
    {
        $ids = $request->input('item_product_id', []);
        $qtys = $request->input('item_qty', []);
        $out = [];
        foreach ($ids as $i => $pid) {
            $qty = (int) ($qtys[$i] ?? 0);
            $pid = (int) $pid;
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }
            $p = Product::find($pid);
            if (! $p) {
                continue;
            }
            $out[] = ['product_id' => $pid, 'sku' => $p->sku, 'name' => $p->name, 'qty' => $qty];
        }

        return $out;
    }
}
