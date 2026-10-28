<?php

namespace App\Http\Controllers;

use App\Models\FinanceAccount;
use App\Models\FinanceFlow;
use App\Models\FinancePrepayment;
use App\Models\FinanceReconciliation;
use App\Services\Finance\FinanceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function __construct(private FinanceService $finance) {}

    /** 财务总览：账户余额、本月收支净额、近期流水、未核销预付 */
    public function index()
    {
        $accounts = FinanceAccount::where('is_active', true)->orderBy('id')->get();

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $income = (float) FinanceFlow::where('direction', FinanceFlow::DIRECTION_INCOME)
            ->whereBetween('occurred_on', [$monthStart, $monthEnd])->sum('amount');
        $expense = (float) FinanceFlow::where('direction', FinanceFlow::DIRECTION_EXPENSE)
            ->whereBetween('occurred_on', [$monthStart, $monthEnd])->sum('amount');

        $totalBalance = (float) $accounts->sum('balance');
        $openPrepay = (float) FinancePrepayment::whereIn('status', [FinancePrepayment::STATUS_PENDING, FinancePrepayment::STATUS_PARTIAL])
            ->where('direction', FinancePrepayment::DIRECTION_OUT)
            ->selectRaw('COALESCE(SUM(amount - used_amount),0) as s')->value('s');

        $recent = FinanceFlow::with('account')->latest()->limit(12)->get();
        $recons = FinanceReconciliation::with('account')->latest()->limit(5)->get();

        return view('finance.index', compact(
            'accounts', 'income', 'expense', 'totalBalance', 'openPrepay', 'recent', 'recons'
        ));
    }

    /** 资金账户管理 */
    public function accounts()
    {
        $accounts = FinanceAccount::withCount('flows')->orderBy('id')->get();

        return view('finance.accounts', compact('accounts'));
    }

    public function accountStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:60',
            'type' => 'required|in:cash,bank,alipay,wechat,other',
            'note' => 'nullable|string|max:255',
        ]);
        FinanceAccount::create($data + ['balance' => 0, 'is_active' => true]);

        return redirect()->route('finance.accounts')->with('ok', '账户已创建');
    }

    public function accountToggle(FinanceAccount $account)
    {
        $account->update(['is_active' => ! $account->is_active]);

        return back()->with('ok', $account->is_active ? '账户已启用' : '账户已停用');
    }

    public function accountDestroy(FinanceAccount $account)
    {
        if ($account->flows()->exists()) {
            return back()->with('err', '该账户已有流水，无法删除');
        }
        $account->delete();

        return back()->with('ok', '账户已删除');
    }

    /** 收支流水 */
    public function flows(Request $request)
    {
        $direction = $request->query('direction');
        $accountId = $request->query('account_id');

        $query = FinanceFlow::with('account', 'creator')->latest();
        if (in_array($direction, [FinanceFlow::DIRECTION_INCOME, FinanceFlow::DIRECTION_EXPENSE], true)) {
            $query->where('direction', $direction);
        }
        if ($accountId) {
            $query->where('account_id', $accountId);
        }
        $list = $query->paginate(20)->withQueryString();
        $accounts = FinanceAccount::orderBy('id')->get();

        return view('finance.flows', compact('list', 'accounts', 'direction', 'accountId'));
    }

    public function flowStore(Request $request)
    {
        $data = $request->validate([
            'direction' => 'required|in:income,expense',
            'category' => 'required|string|max:30',
            'amount' => 'required|numeric|min:0.01',
            'account_id' => 'required|exists:finance_accounts,id',
            'occurred_on' => 'nullable|date',
            'remark' => 'nullable|string|max:255',
        ]);

        $account = FinanceAccount::find($data['account_id']);
        $this->finance->record(
            $account, $data['direction'], $data['category'], (float) $data['amount'],
            $data['occurred_on'] ?? null, $data['remark'] ?? null, 'manual', null, $request->user()->id,
        );

        return redirect()->route('finance.flows')->with('ok', '流水已登记，账户余额已更新');
    }

    public function flowDestroy(FinanceFlow $flow)
    {
        $this->finance->remove($flow);

        return back()->with('ok', '流水已删除，账户余额已冲销');
    }

    // ---------------------------------------------------------------- 账务台账

    /**
     * 账务台账：账户明细账（期初 / 期间收支 / 期末）+ 科目总账 + 期间流水
     *
     * 期初余额口径：账户当前余额 − 统计起始日以来的累计净发生额（收入 − 支出）
     * 期末余额 = 期初 + 期间收入 − 期间支出；统计区间覆盖至今时，期末 = 当前余额
     */
    public function ledger(Request $request)
    {
        $from = $this->ledgerFrom($request);
        $to = $this->ledgerTo($request);
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $accountId = $request->query('account_id');

        $accountQuery = FinanceAccount::orderBy('id');
        if ($accountId) {
            $accountQuery->whereKey($accountId);
        }
        $accounts = $accountQuery->get();

        // ---- 账户明细账 ----
        $ledgerRows = $accounts->map(function ($a) use ($from, $to) {
            $periodIn = (float) FinanceFlow::where('account_id', $a->id)
                ->where('direction', FinanceFlow::DIRECTION_INCOME)
                ->whereBetween('occurred_on', [$from, $to])->sum('amount');
            $periodOut = (float) FinanceFlow::where('account_id', $a->id)
                ->where('direction', FinanceFlow::DIRECTION_EXPENSE)
                ->whereBetween('occurred_on', [$from, $to])->sum('amount');
            $netAfterFrom = (float) FinanceFlow::where('account_id', $a->id)
                ->where('occurred_on', '>=', $from)
                ->selectRaw("SUM(CASE WHEN direction = 'income' THEN amount ELSE -amount END) AS s")
                ->value('s');
            $opening = round((float) $a->balance - $netAfterFrom, 2);

            return [
                'account' => $a,
                'opening' => $opening,
                'income' => $periodIn,
                'expense' => $periodOut,
                'closing' => round($opening + $periodIn - $periodOut, 2),
                'flows' => (int) FinanceFlow::where('account_id', $a->id)
                    ->whereBetween('occurred_on', [$from, $to])->count(),
            ];
        });

        // ---- 科目总账（按分类汇总） ----
        $catQuery = FinanceFlow::query()
            ->whereBetween('occurred_on', [$from, $to])
            ->selectRaw('category, direction, SUM(amount) AS total, COUNT(*) AS cnt')
            ->groupBy('category', 'direction')
            ->orderByDesc('total');
        if ($accountId) {
            $catQuery->where('account_id', $accountId);
        }
        $byCategory = $catQuery->get();
        $maxCatTotal = max(1.0, (float) $byCategory->max('total'));

        // ---- 期间流水（分页） ----
        $flowQuery = FinanceFlow::with('account')->whereBetween('occurred_on', [$from, $to]);
        if ($accountId) {
            $flowQuery->where('account_id', $accountId);
        }
        $flows = $flowQuery->latest('occurred_on')->latest('id')->paginate(30)->withQueryString();

        $sumIn = (float) FinanceFlow::where('direction', FinanceFlow::DIRECTION_INCOME)
            ->whereBetween('occurred_on', [$from, $to])
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))->sum('amount');
        $sumOut = (float) FinanceFlow::where('direction', FinanceFlow::DIRECTION_EXPENSE)
            ->whereBetween('occurred_on', [$from, $to])
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))->sum('amount');

        return view('finance.ledger', compact(
            'from', 'to', 'accountId', 'accounts', 'ledgerRows', 'byCategory', 'maxCatTotal',
            'flows', 'sumIn', 'sumOut'
        ));
    }

    /** 台账流水导出 CSV（带 BOM，Excel 打开不乱码） */
    public function ledgerExport(Request $request): StreamedResponse
    {
        $from = $this->ledgerFrom($request);
        $to = $this->ledgerTo($request);
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $accountId = $request->query('account_id');

        $query = FinanceFlow::with('account')->whereBetween('occurred_on', [$from, $to]);
        if ($accountId) {
            $query->where('account_id', $accountId);
        }
        $rows = $query->latest('occurred_on')->latest('id')->get();

        $fileName = 'ledger_' . $from . '_' . $to . '.csv';

        return response()->streamDownload(function () use ($rows, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ["账务台账（{$from} ~ {$to}）"]);
            fputcsv($out, ['日期', '单号', '账户', '方向', '科目', '金额', '备注']);
            foreach ($rows as $f) {
                fputcsv($out, [
                    $f->occurred_on?->format('Y-m-d'),
                    $f->flow_no,
                    $f->account?->name ?? '—',
                    $f->directionLabel(),
                    $f->categoryLabel($f->category),
                    number_format((float) $f->amount, 2, '.', ''),
                    $f->remark,
                ]);
            }
            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function ledgerFrom(Request $request): string
    {
        $from = (string) $request->query('date_from', '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : now()->startOfMonth()->toDateString();
    }

    private function ledgerTo(Request $request): string
    {
        $to = (string) $request->query('date_to', '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : now()->endOfMonth()->toDateString();
    }

    /** 预付款 / 预收款管理 */
    public function prepayments()
    {
        $list = FinancePrepayment::with('account', 'creator')->latest()->paginate(20);
        $accounts = FinanceAccount::where('is_active', true)->orderBy('id')->get();

        return view('finance.prepayments', compact('list', 'accounts'));
    }

    public function prepayStore(Request $request)
    {
        $data = $request->validate([
            'direction' => 'required|in:out,in',
            'party_name' => 'required|string|max:96',
            'amount' => 'required|numeric|min:0.01',
            'account_id' => 'required|exists:finance_accounts,id',
            'occurred_on' => 'nullable|date',
            'remark' => 'nullable|string|max:255',
        ]);

        $account = FinanceAccount::find($data['account_id']);
        $this->finance->createPrepayment(
            $account, $data['direction'], $data['party_name'], (float) $data['amount'],
            $data['occurred_on'] ?? null, $data['remark'] ?? null, $request->user()->id,
        );

        return redirect()->route('finance.prepayments')->with('ok', '已登记，资金已' . ($data['direction'] === 'out' ? '付出' : '收取'));
    }

    public function prepayApply(Request $request, FinancePrepayment $prepay)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0.01']);
        $this->finance->applyPrepayment($prepay, (float) $data['amount']);

        return back()->with('ok', '已核销 ¥' . number_format(min($data['amount'], $prepay->remaining), 2));
    }

    public function prepayCancel(FinancePrepayment $prepay)
    {
        $this->finance->cancelPrepayment($prepay, auth()->id());

        return back()->with('ok', '预付款已取消，资金已退回账户');
    }

    /** 对账 */
    public function reconciliations()
    {
        $list = FinanceReconciliation::with('account', 'creator')->latest()->paginate(20);
        $accounts = FinanceAccount::where('is_active', true)->orderBy('id')->get();

        return view('finance.reconciliations', compact('list', 'accounts'));
    }

    public function reconcileStore(Request $request)
    {
        $data = $request->validate([
            'account_id' => 'required|exists:finance_accounts,id',
            'actual_balance' => 'required|numeric',
            'note' => 'nullable|string|max:255',
        ]);

        $account = FinanceAccount::find($data['account_id']);
        $recon = $this->finance->reconcile($account, (float) $data['actual_balance'], $data['note'] ?? null, $request->user()->id);

        $msg = $recon->difference === 0.0 ? '已平账 ✓' : '存在差异 ¥' . number_format($recon->difference, 2);

        return redirect()->route('finance.reconciliations')->with('ok', '对账完成：' . $msg);
    }
}
