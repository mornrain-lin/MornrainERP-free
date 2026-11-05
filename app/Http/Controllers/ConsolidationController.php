<?php

namespace App\Http\Controllers;

use App\Models\ConsolidationSnapshot;
use App\Services\Consolidation\ConsolidationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 跨组织合并报表。
 *
 * 安全：合并报表天然跨组织，因此除 consolidation.view 权限外，还要求
 * 所选合并根节点必须落在当前用户可访问的组织范围内（OrgContext），
 * 防止普通用户通过改 URL 窥探兄弟公司的账簿。
 */
class ConsolidationController extends Controller
{
    public function __construct(private ConsolidationService $consolidation) {}

    public function index(Request $request)
    {
        [$rootId, $candidates] = $this->resolveRoot($request);
        [$from, $to] = $this->consolidation->normalizePeriod(
            $this->strQuery($request, 'date_from'),
            $this->strQuery($request, 'date_to'),
        );

        $report = $this->consolidation->build($rootId, $from, $to);

        return view('consolidation.index', compact('report', 'candidates', 'rootId'));
    }

    /** 导出合并报表 CSV（组织明细 + 分类汇总 + 抵消明细） */
    public function export(Request $request): StreamedResponse
    {
        [$rootId] = $this->resolveRoot($request);
        [$from, $to] = $this->consolidation->normalizePeriod(
            $this->strQuery($request, 'date_from'),
            $this->strQuery($request, 'date_to'),
        );

        $report = $this->consolidation->build($rootId, $from, $to);
        $t = $report['totals'];
        $filename = "consolidation_{$rootId}_{$from}_{$to}.csv";

        return response()->streamDownload(function () use ($report, $t) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM，Excel 不乱码

            fputcsv($out, ["合并报表（{$report['from']} ~ {$report['to']}）"]);
            fputcsv($out, ['合并根节点', $report['root']->name, '本位币', $report['currency'], '组织数', $t['org_count']]);
            fputcsv($out, []);

            fputcsv($out, ['【组织明细】']);
            fputcsv($out, ['组织', '层级', '毛收入', '毛支出', '抵消收入', '抵消支出', '合并收入', '合并支出', '合并净额', '期末余额', '流水数']);
            foreach ($report['rows'] as $r) {
                fputcsv($out, [
                    $r['org']->name,
                    $r['org']->typeLabel(),
                    $this->num($r['gross_income']), $this->num($r['gross_expense']),
                    $this->num($r['eliminated_income']), $this->num($r['eliminated_expense']),
                    $this->num($r['income']), $this->num($r['expense']),
                    $this->num($r['net']), $this->num($r['balance']), $r['flow_count'],
                ]);
            }
            fputcsv($out, [
                '合计', '',
                $this->num($t['gross_income']), $this->num($t['gross_expense']),
                $this->num($t['eliminated_income']), $this->num($t['eliminated_expense']),
                $this->num($t['income']), $this->num($t['expense']),
                $this->num($t['net']), $this->num($t['balance']), $t['flow_count'],
            ]);
            fputcsv($out, []);

            fputcsv($out, ['【分类科目（抵消后）】']);
            fputcsv($out, ['方向', '科目', '毛额', '抵消额', '合并数']);
            foreach ($report['categories'] as $c) {
                fputcsv($out, [$c['direction_label'], $c['label'], $this->num($c['gross']), $this->num($c['eliminated']), $this->num($c['net'])]);
            }
            fputcsv($out, []);

            fputcsv($out, ['【本期抵消的内部往来】']);
            fputcsv($out, ['单号', '日期', '付款方', '收款方', '类型', '金额', '抵消支出', '抵消收入']);
            foreach ($report['eliminations'] as $e) {
                fputcsv($out, [
                    $e['txn']->txn_no,
                    $e['txn']->occurred_on?->format('Y-m-d'),
                    $e['from'], $e['to'], $e['category'],
                    $this->num($e['amount']), $this->num($e['eliminated_expense']), $this->num($e['eliminated_income']),
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['【范围外内部往来（不抵消）】']);
            fputcsv($out, ['单号', '日期', '付款方', '收款方', '金额']);
            foreach ($report['external'] as $e) {
                fputcsv($out, [
                    $e['txn']->txn_no,
                    $e['txn']->occurred_on?->format('Y-m-d'),
                    $e['from'], $e['to'], $this->num($e['amount']),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** 保存当前合并快照（固化结果，便于审计留档与跨期对比） */
    public function snapshot(Request $request): \Illuminate\Http\RedirectResponse
    {
        [$rootId] = $this->resolveRoot($request);
        [$from, $to] = $this->consolidation->normalizePeriod(
            $request->input('date_from'),
            $request->input('date_to'),
        );

        $this->consolidation->snapshot($rootId, $from, $to, $request->user()?->id);

        return redirect()
            ->route('consolidation.index', ['root_org_id' => $rootId, 'date_from' => $from, 'date_to' => $to])
            ->with('ok', "已保存合并快照（{$from} ~ {$to}）");
    }

    /** 某合并根节点的快照列表 */
    public function snapshots(Request $request)
    {
        [$rootId, $candidates] = $this->resolveRoot($request);

        $snapshots = $this->consolidation->snapshotsFor($rootId);

        return view('consolidation.snapshots', compact('snapshots', 'candidates', 'rootId'));
    }

    /** 查看某一合并快照（只读回放固化结果） */
    public function showSnapshot(Request $request, ConsolidationSnapshot $snapshot)
    {
        $user = $request->user();
        abort_unless(
            $this->consolidation->userCanUseRoot($user, (int) $snapshot->root_org_id),
            403,
            '该快照所属合并根节点不在当前账号可访问的组织范围内'
        );

        $data = $snapshot->report_data ?? [];

        return view('consolidation.snapshot', compact('snapshot', 'data'));
    }

    /** @return array{0: int, 1: \Illuminate\Support\Collection} */
    private function resolveRoot(Request $request): array
    {
        $user = $request->user();
        $candidates = $this->consolidation->rootCandidates($user);

        abort_if($candidates->isEmpty(), 403, '当前账号没有可访问的组织');

        $picked = $request->input('root_org_id');
        $rootId = is_numeric($picked) ? (int) $picked : (int) $candidates->first()->id;

        abort_unless(
            $this->consolidation->userCanUseRoot($user, $rootId),
            403,
            '所选合并根节点不在当前账号可访问的组织范围内'
        );

        return [$rootId, $candidates];
    }

    private function strQuery(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : null;
    }

    private function num(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
