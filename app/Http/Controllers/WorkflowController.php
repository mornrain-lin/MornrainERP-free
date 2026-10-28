<?php

namespace App\Http\Controllers;

use App\Models\WorkflowInstance;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    /** 流程目录：全部 50+ 电子流程模板，按分类展示 */
    public function index()
    {
        $cats = WorkflowService::allDefinitions();
        $total = WorkflowService::totalCount();

        return view('workflows.catalog', compact('cats', 'total'));
    }

    /** 发起申请：按模板动态渲染表单 */
    public function create(Request $request, string $key)
    {
        $def = WorkflowService::definition($key);
        abort_if(is_null($def), 404, '流程模板不存在');

        return view('workflows.create', compact('def', 'key'));
    }

    /** 提交申请 */
    public function store(Request $request)
    {
        $key = (string) $request->input('workflow_key');
        $def = WorkflowService::definition($key);
        abort_if(is_null($def), 404, '流程模板不存在');

        $data = $request->except(['_token', 'workflow_key']);
        $inst = (new WorkflowService())->submit($request->user(), $key, $data);

        return redirect()->route('workflows.show', $inst)->with('ok', '已提交，进入审批流程');
    }

    /** 我的待办：别人发起、当前审批中的流程 */
    public function todo()
    {
        $list = WorkflowInstance::where('status', WorkflowInstance::STATUS_PENDING)
            ->where('applicant_id', '!=', auth()->id())
            ->latest()
            ->paginate(20);

        return view('workflows.list', [
            'list' => $list,
            'title' => '我的待办',
            'desc' => '等待你审批的流程',
            'empty' => '暂无待办 🎉',
        ]);
    }

    /** 我发起的 */
    public function my()
    {
        $list = WorkflowInstance::where('applicant_id', auth()->id())
            ->latest()
            ->paginate(20);

        return view('workflows.list', [
            'list' => $list,
            'title' => '我发起的',
            'desc' => '你提交的全部流程',
            'empty' => '你还没有发起过流程',
        ]);
    }

    /** 流程详情 */
    public function show(WorkflowInstance $instance)
    {
        $def = WorkflowService::definition($instance->workflow_key);
        $instance->load('logs.actor', 'applicant');

        return view('workflows.show', compact('instance', 'def'));
    }

    /** 审批通过当前节点 */
    public function approve(Request $request, WorkflowInstance $instance)
    {
        abort_if($instance->status !== WorkflowInstance::STATUS_PENDING, 400, '该流程已结束');
        (new WorkflowService())->approve($instance, $request->user(), $request->input('comment'));
        $msg = $instance->fresh()->status === WorkflowInstance::STATUS_APPROVED
            ? '已全部审批通过 ✅'
            : '已通过当前节点，流转至下一审批人';

        return back()->with('ok', $msg);
    }

    /** 驳回 */
    public function reject(Request $request, WorkflowInstance $instance)
    {
        abort_if($instance->status !== WorkflowInstance::STATUS_PENDING, 400, '该流程已结束');
        (new WorkflowService())->reject($instance, $request->user(), $request->input('comment'));

        return back()->with('err', '已驳回该申请');
    }
}
