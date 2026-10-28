<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Services\Audit\AuditService;
use App\Services\Purchase\PurchaseRequisitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 请购申请（采购闭环第一环）
 *
 * draft 草稿 → pending 审批中（流程中心审批）→ approved 已通过 → converted 已转采购单
 * 提交 / 撤回 / 转采购单等状态动作全部走 PurchaseRequisitionService。
 */
class RequisitionController extends Controller
{
    public function __construct(private PurchaseRequisitionService $service) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');

        // LIKE 通配符转义，避免用户输入 %/_ 改变匹配语义
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';

        $list = PurchaseRequisition::with(['applicant', 'items'])
            ->withCount('items')
            ->when($q !== '', function ($b) use ($like) {
                $b->where(function ($w) use ($like) {
                    $w->where('requisition_no', 'like', $like)
                        ->orWhere('department', 'like', $like)
                        ->orWhere('remark', 'like', $like)
                        ->orWhereHas('applicant', fn ($u) => $u->where('name', 'like', $like));
                });
            })
            ->when($status !== 'all', fn ($b) => $b->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('requisitions.index', compact('list', 'q', 'status'));
    }

    public function create()
    {
        $products = Product::orderBy('sku')->get(['id', 'sku', 'name', 'cost_price']);

        return view('requisitions.form', [
            'req' => new PurchaseRequisition(),
            'products' => $products,
            'items' => collect(),
            'editing' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $req = $this->service->create($request->user(), $data);

        app(AuditService::class)->log(
            'requisition.created',
            "创建请购单 {$req->requisition_no}（{$req->department} · " . count($data['items']) . ' 个 SKU）',
            $req
        );

        return redirect()->route('requisitions.show', $req)
            ->with('ok', "请购单 {$req->requisition_no} 已创建（草稿）");
    }

    public function show(PurchaseRequisition $requisition)
    {
        $requisition->load(['items.product', 'applicant', 'approver', 'purchaseOrder.supplier', 'workflowInstance']);
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('requisitions.show', compact('requisition', 'suppliers'));
    }

    public function edit(PurchaseRequisition $requisition)
    {
        if (! in_array($requisition->status, [PurchaseRequisition::STATUS_DRAFT, PurchaseRequisition::STATUS_REJECTED], true)) {
            return redirect()->route('requisitions.show', $requisition)
                ->with('err', '仅「草稿 / 已驳回」状态可编辑');
        }

        $products = Product::orderBy('sku')->get(['id', 'sku', 'name', 'cost_price']);
        $items = $requisition->items->map(fn ($i) => [
            'sku' => $i->sku,
            'product_name' => $i->product_name,
            'quantity' => (int) $i->quantity,
            'unit_cost' => (float) $i->unit_cost,
        ])->values();

        return view('requisitions.form', [
            'req' => $requisition,
            'products' => $products,
            'items' => $items,
            'editing' => true,
        ]);
    }

    public function update(Request $request, PurchaseRequisition $requisition): RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $this->service->update($requisition, $data);
        } catch (RuntimeException $e) {
            return back()->with('err', $e->getMessage());
        }

        app(AuditService::class)->log('requisition.updated', "修改请购单 {$requisition->requisition_no}", $requisition);

        return redirect()->route('requisitions.show', $requisition)
            ->with('ok', "请购单 {$requisition->requisition_no} 已保存");
    }

    /** 提交审批：发起流程实例，进入审批中 */
    public function submit(PurchaseRequisition $requisition): RedirectResponse
    {
        try {
            $this->service->submit($requisition);
        } catch (RuntimeException $e) {
            return back()->with('err', $e->getMessage());
        }

        app(AuditService::class)->log('requisition.submitted', "请购单 {$requisition->requisition_no} 提交审批", $requisition);

        return redirect()->route('requisitions.show', $requisition)
            ->with('ok', "请购单 {$requisition->requisition_no} 已提交，等待审批（可在「流程中心 → 我的待办」查看）");
    }

    /** 撤回审批中的请购单 */
    public function cancel(PurchaseRequisition $requisition): RedirectResponse
    {
        try {
            $this->service->cancel($requisition);
        } catch (RuntimeException $e) {
            return back()->with('err', $e->getMessage());
        }

        app(AuditService::class)->log('requisition.canceled', "撤回请购单 {$requisition->requisition_no}", $requisition);

        return redirect()->route('requisitions.show', $requisition)
            ->with('ok', "请购单 {$requisition->requisition_no} 已撤回");
    }

    /** 审批通过后转采购单（草稿态，后续在采购模块完成下单 / 收货） */
    public function convert(Request $request, PurchaseRequisition $requisition): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
        ]);

        try {
            $po = $this->service->convert($requisition, (int) $data['supplier_id']);
        } catch (RuntimeException $e) {
            return back()->with('err', $e->getMessage());
        }

        app(AuditService::class)->log(
            'requisition.converted',
            "请购单 {$requisition->requisition_no} 转采购单 {$po->po_no}（¥" . number_format($po->total_amount, 2) . '）',
            $requisition
        );

        return redirect()->route('purchases.show', $po)
            ->with('ok', "已生成采购单 {$po->po_no}，请继续完成下单与收货");
    }

    public function destroy(PurchaseRequisition $requisition): RedirectResponse
    {
        if ($requisition->status !== PurchaseRequisition::STATUS_DRAFT) {
            return back()->with('err', '仅「草稿」状态可删除');
        }

        $no = $requisition->requisition_no;
        $requisition->items()->delete();
        $requisition->delete();

        app(AuditService::class)->log('requisition.deleted', "删除请购单 {$no}");

        return redirect()->route('requisitions.index')->with('ok', "请购单 {$no} 已删除");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'department' => ['required', 'string', 'max:60'],
            'expected_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:normal,urgent'],
            'remark' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['required', 'string', 'max:64'],
            'items.*.product_name' => ['nullable', 'string', 'max:160'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
