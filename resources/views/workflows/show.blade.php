@extends('layouts.app')

@section('title', '流程详情 · ' . $instance->title)
@section('desc', $instance->category . ' · 单号 #' . $instance->id)

@section('actions')
    <a class="btn" href="{{ route('workflows.my') }}">我发起的</a>
    <a class="btn" href="{{ route('workflows.todo') }}">待办</a>
@endsection

@section('content')
    <div class="grid grid-2">
        <div class="card">
            <div class="card-head">
                <h2 class="card-title">申请信息</h2>
                <span class="badge badge-{{ \App\Models\WorkflowInstance::statusTone($instance->status) }}">
                    {{ \App\Models\WorkflowInstance::statusLabel($instance->status) }}
                </span>
            </div>
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-item"><div class="k">发起人</div><div class="v">{{ $instance->applicant?->name ?? '—' }}</div></div>
                    <div class="detail-item"><div class="k">当前节点</div><div class="v">
                        @if ($instance->status === \App\Models\WorkflowInstance::STATUS_PENDING)
                            {{ ($def['nodes'][$instance->current_node] ?? '—') }}（{{ $instance->current_node + 1 }}/{{ $instance->node_count }}）
                        @else
                            已结束
                        @endif
                    </div></div>
                    <div class="detail-item"><div class="k">提交时间</div><div class="v">{{ $instance->created_at->format('Y-m-d H:i') }}</div></div>
                    @if ($instance->approved_at)
                        <div class="detail-item"><div class="k">办结时间</div><div class="v">{{ $instance->approved_at->format('Y-m-d H:i') }}</div></div>
                    @endif
                </div>

                @if (!empty($instance->data))
                    <div class="section-title" style="margin-top:14px">表单内容</div>
                    <div class="detail-grid">
                        @foreach ($instance->data as $k => $v)
                            @if (!str_starts_with($k, '_'))
                                <div class="detail-item"><div class="k">{{ $k }}</div><div class="v">{{ is_array($v) ? implode(',', $v) : $v }}</div></div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h2 class="card-title">审批流转</h2>
            </div>
            <div class="card-body">
                @if ($instance->logs->isNotEmpty())
                    <ul class="timeline">
                        @foreach ($instance->logs as $log)
                            <li>
                                <span class="t-status">{{ \App\Models\WorkflowInstanceLog::actionLabel($log->action) }}
                                    @if ($log->action === \App\Models\WorkflowInstanceLog::ACTION_APPROVE && $instance->node_count > 1)
                                        · 节点 {{ $log->node + 1 }}/{{ $instance->node_count }}
                                    @endif
                                </span>
                                <span class="t-time">{{ $log->actor_name }} · {{ $log->created_at->format('Y-m-d H:i') }}</span>
                                @if ($log->comment)<div class="t-desc">{{ $log->comment }}</div>@endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="empty">暂无流转记录</div>
                @endif

                @if ($instance->status === \App\Models\WorkflowInstance::STATUS_PENDING)
                    <form method="post" action="{{ route('workflows.approve', $instance) }}" class="wf-act">
                        @csrf
                        <input type="text" name="comment" placeholder="审批意见（可选）" class="wf-comment">
                        <button class="btn btn-primary" type="submit">通过本节点</button>
                    </form>
                    <form method="post" action="{{ route('workflows.reject', $instance) }}" class="wf-act">
                        @csrf
                        <input type="text" name="comment" placeholder="驳回理由（可选）" class="wf-comment">
                        <button class="btn btn-danger" type="submit">驳回</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
