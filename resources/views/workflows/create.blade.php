@extends('layouts.app')

@section('title', '发起申请 · ' . ($def['name'] ?? ''))
@section('desc', $def['desc'] ?? '')

@section('actions')
    <a class="btn" href="{{ route('workflows.index') }}">← 流程目录</a>
@endsection

@section('content')
    <div class="card" style="max-width:720px">
        <div class="card-head">
            <h2 class="card-title">{{ $def['name'] }}</h2>
            <div class="card-desc">{{ $def['category'] ?? '' }} · 审批链：{{ implode(' → ', $def['nodes'] ?? []) }}</div>
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('workflows.store') }}">
                @csrf
                <input type="hidden" name="workflow_key" value="{{ $key }}">

                <div class="form-grid">
                    @foreach ($def['fields'] ?? [] as $f)
                        <div class="field">
                            <label>{{ $f['label'] }}@if (!empty($f['required'])) <span class="req">*</span>@endif</label>
                            @if (($f['type'] ?? 'text') === 'textarea')
                                <textarea name="{{ $f['key'] }}" rows="3" {{ !empty($f['required']) ? 'required' : '' }}></textarea>
                            @elseif (($f['type'] ?? 'text') === 'select')
                                <select name="{{ $f['key'] }}" {{ !empty($f['required']) ? 'required' : '' }}>
                                    <option value="">请选择</option>
                                    @foreach ($f['options'] ?? [] as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="{{ $f['type'] === 'number' ? 'number' : ($f['type'] === 'date' ? 'date' : 'text') }}"
                                       name="{{ $f['key'] }}" {{ !empty($f['required']) ? 'required' : '' }}>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="btn-row" style="margin-top:16px">
                    <button class="btn btn-primary" type="submit">提交申请</button>
                    <a class="btn" href="{{ route('workflows.index') }}">取消</a>
                </div>
            </form>
        </div>
    </div>
@endsection
