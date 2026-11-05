@extends('layouts.app')

@section('title', '合并快照')
@section('desc', '已固化的合并结果 · 审计留档与跨期对比')

@section('actions')
    <a class="btn btn-primary" href="{{ route('consolidation.index', ['root_org_id' => $rootId]) }}">返回合并报表</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-head">
            <h2 class="card-title">合并快照列表</h2>
            <span class="muted small">合并主体：{{ $candidates->firstWhere('id', $rootId)?->name ?? '—' }}</span>
        </div>
        <div class="card-body tight">
            <div class="table-wrap">
                <table class="tbl">
                    <thead>
                    <tr>
                        <th>期间</th><th>合并主体</th><th class="num">合并净利润</th>
                        <th class="num">少数股东损益</th><th class="num">归母净利润</th>
                        <th class="num">期末未实现利润</th><th>生成时间</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($snapshots as $s)
                        @php
                            $d = $s->report_data ?? [];
                            $t = $d['totals'] ?? [];
                        @endphp
                        <tr>
                            <td class="mono">{{ $s->period_from?->format('Y-m-d') }} ~ {{ $s->period_to?->format('Y-m-d') }}</td>
                            <td class="strong">{{ $d['root_name'] ?? '—' }}</td>
                            <td class="num">¥{{ number_format((float) ($t['net'] ?? 0), 2) }}</td>
                            <td class="num down">− ¥{{ number_format((float) ($t['minority_interest'] ?? 0), 2) }}</td>
                            <td class="num">¥{{ number_format((float) ($t['net_attributable'] ?? 0), 2) }}</td>
                            <td class="num">¥{{ number_format((float) ($t['unrealized_closing'] ?? 0), 2) }}</td>
                            <td class="muted">{{ $s->created_at?->format('Y-m-d H:i') }}</td>
                            <td><a class="btn btn-sm" href="{{ route('consolidation.snapshot.show', $s) }}">查看</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty">还没有保存过合并快照</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
