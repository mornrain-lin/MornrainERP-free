<?php

namespace App\Http\Controllers;

use App\Models\ReportTemplate;
use App\Models\User;
use App\Services\ReportDesign\ReportDesignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ReportDesignController extends Controller
{
    public function __construct(private ReportDesignService $service) {}

    public function index(): View
    {
        $templates = ReportTemplate::with('creator')->orderByDesc('id')->get();
        $stats = [
            'total' => $templates->count(),
            'active' => $templates->where('is_active', true)->count(),
            'sources' => count(ReportDesignService::sources()),
        ];

        return view('report-design.index', compact('templates', 'stats'));
    }

    public function create(): View
    {
        $sources = ReportDesignService::sources();

        return view('report-design.create', compact('sources'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTemplate($request);

        $template = $this->service->createTemplate($request->user(), $data);

        return redirect()->route('report-design.show', $template)
            ->with('success', "报表「{$template->name}」已创建，可立即运行查看结果。");
    }

    public function show(ReportTemplate $template, Request $request): View
    {
        abort_if(! $template->is_active, 404);

        $input = $request->all();
        $rows = $this->service->run($template, $input);
        $source = ReportDesignService::source($template->data_source);
        $columns = $this->resolveColumns($template);

        return view('report-design.show', compact('template', 'rows', 'columns', 'source', 'input'));
    }

    public function edit(ReportTemplate $template): View
    {
        $sources = ReportDesignService::sources();

        return view('report-design.edit', compact('template', 'sources'));
    }

    public function update(Request $request, ReportTemplate $template): RedirectResponse
    {
        $data = $this->validateTemplate($request, $template);

        $this->service->updateTemplate($request->user(), $template, $data);

        return redirect()->route('report-design.show', $template)
            ->with('success', "报表「{$template->name}」已更新。");
    }

    public function toggle(Request $request, ReportTemplate $template): RedirectResponse
    {
        $this->service->toggle($request->user(), $template);

        return back()->with('success', "报表「{$template->name}」已".($template->is_active ? '启用' : '停用').'。');
    }

    public function destroy(Request $request, ReportTemplate $template): RedirectResponse
    {
        $name = $template->name;
        $this->service->deleteTemplate($request->user(), $template);

        return redirect()->route('report-design.index')->with('success', "报表「{$name}」已删除。");
    }

    public function export(ReportTemplate $template, Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_if(! $template->is_active, 404);

        $rows = $this->service->run($template, $request->all());
        $columns = $this->resolveColumns($template);
        $source = ReportDesignService::source($template->data_source);

        $filename = 'report_'.$template->code.'_'.date('Ymd_His').'.csv';

        return response()->stream(function () use ($rows, $columns, $source) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM 便于 Excel 打开
            fputcsv($handle, array_column($columns, 'label'));
            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $col) {
                    $line[] = $this->service->formatValue($row->{$col['field']} ?? null, $col['type']);
                }
                fputcsv($handle, $line);
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * 解析模板列（补充类型信息供视图格式化）
     */
    private function resolveColumns(ReportTemplate $template): Collection
    {
        return collect($template->columns)->map(fn (array $col) => [
            'field' => $col['field'],
            'label' => $col['label'] ?? $col['field'],
            'type' => $this->service->columnType($template->data_source, $col['field']),
        ]);
    }

    /**
     * 校验并规范化模板数据（列/筛选白名单清洗）
     *
     * 表单结构：
     *  - col_fields[field] = "field|label"（勾选列）
     *  - filter_fields[field] = "operator"（勾选筛选项）
     */
    private function validateTemplate(Request $request, ?ReportTemplate $template = null): array
    {
        $unique = $template ? 'unique:report_templates,code,'.$template->id : 'unique:report_templates,code';
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', $unique],
            'description' => ['nullable', 'string', 'max:255'],
            'data_source' => ['required', 'string', 'in:'.implode(',', ReportDesignService::sourceKeys())],
        ], [], [
            'name' => '报表名称',
            'code' => '报表编码',
            'data_source' => '数据源',
        ]);

        $source = ReportDesignService::source($data['data_source']);
        $fieldMap = collect($source['fields'])->pluck('label', 'field');
        $filterWhitelist = collect($source['filters'])->map(fn ($f) => $f['field'].':'.$f['operator']);

        // 解析勾选列
        $columns = [];
        foreach ($request->input('col_fields', []) as $field => $meta) {
            if (! is_string($field) || ! $fieldMap->has($field)) {
                continue;
            }
            [$f, $label] = array_pad(explode('|', (string) $meta, 2), 2, '');
            $columns[] = ['field' => $f, 'label' => $label !== '' ? $label : $fieldMap[$f]];
        }
        $data['columns'] = collect($columns)->take(20)->values()->all();

        if (empty($data['columns'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['col_fields' => '至少勾选一列展示字段']);
        }

        // 解析勾选筛选项
        $filters = [];
        foreach ($request->input('filter_fields', []) as $field => $operator) {
            $key = $field.':'.$operator;
            if (! is_string($field) || ! $filterWhitelist->contains($key)) {
                continue;
            }
            $filters[] = ['field' => $field, 'operator' => $operator, 'label' => $fieldMap[$field] ?? $field];
        }
        $data['filters'] = collect($filters)->take(10)->values()->all();

        return $data;
    }
}
