<?php

namespace App\Http\Controllers;

use App\Models\FinanceAccount;
use App\Models\OrgIntercompanyTransaction;
use App\Models\Organization;
use App\Services\Consolidation\IntercompanyService;
use Illuminate\Http\Request;

/**
 * 组织间内部往来登记（合并抵消的唯一依据）。
 *
 * 登记会生成一对镜像流水；作废则成对红冲。视图中的账户按组织分组，
 * 后端 IntercompanyService 会强校验「账户归属 = 所选组织」，防止记错账簿。
 */
class IntercompanyController extends Controller
{
    public function __construct(private IntercompanyService $service) {}

    public function index()
    {
        $list = OrgIntercompanyTransaction::with(['fromOrganization', 'toOrganization'])
            ->latest('occurred_on')->latest('id')
            ->paginate(20)->withQueryString();

        $orgs = Organization::where('status', 1)->ordered()->get();

        // 账户按组织分组给下拉用；跨组织页面必须绕过单组织全局作用域
        $accounts = FinanceAccount::withoutGlobalScope('organization')
            ->where('is_active', true)
            ->orderBy('organization_id')->orderBy('id')
            ->get()
            ->groupBy(fn ($a) => (int) $a->organization_id);

        $categories = config('consolidation.categories', []);

        return view('intercompany.index', compact('list', 'orgs', 'accounts', 'categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'from_organization_id' => ['required', 'integer'],
            'to_organization_id' => ['required', 'integer'],
            'from_account_id' => ['required', 'integer'],
            'to_account_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'occurred_on' => ['nullable', 'date'],
            'category' => ['required', 'string', 'max:30'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->record($data, $request->user()?->id);

        return redirect()->route('intercompany.index')
            ->with('ok', '内部往来已登记，已生成付款方/收款方镜像流水；合并报表将自动抵消');
    }

    public function cancel(OrgIntercompanyTransaction $transaction)
    {
        $this->service->cancel($transaction);

        return back()->with('ok', '内部往来已作废，镜像流水已红冲、账户余额已回滚');
    }
}
