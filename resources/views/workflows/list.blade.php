@extends('layouts.app')

@section('title', $title)
@section('desc', $desc ?? '')

@section('actions')
    <a class="btn btn-primary" href="{{ route('workflows.index') }}">流程目录</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">{{ $title }}</h2>
            <div class="card-desc">共 {{ $list->total() }} 条</div>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>单号</th><th>流程</th><th>标题</th><th>发起人</th>
                            <th>状态</th><th>节点</th><th class="num">提交时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($list as $it)
                            <tr>
                                <td class="mono">#{{ $it->id }}</td>
                                <td>{{ $it->category }}</td>
                                <td><a href="{{ route('workflows.show', $it) }}">{{ $it->title }}</a></td>
                                <td>{{ $it->applicant?->name ?? '—' }}</td>
                                <td><span class="badge badge-{{ \App\Models\WorkflowInstance::statusTone($it->status) }}">{{ \App\Models\WorkflowInstance::statusLabel($it->status) }}</span></td>
                                <td>{{ $it->status === \App\Models\WorkflowInstance::STATUS_PENDING ? ($it->current_node + 1) . '/' . $it->node_count : '—' }}</td>
                                <td class="num">{{ $it->created_at->format('m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty">{{ $empty }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($list->hasPages())
            <div class="pagination-wrap">{{ $list->links() }}</div>
        @endif
    </div>
@endsection
