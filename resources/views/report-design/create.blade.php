@extends('layouts.app')

@section('title', '新建报表')
@section('desc', '选择数据源，勾选展示列与筛选项')

@section('actions')
    <a class="btn" href="{{ route('report-design.index') }}">返回列表</a>
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-err">
            <ul style="margin:0;padding-left:18px">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('report-design.store') }}" class="form-grid" id="designerForm">
        @csrf

        <div class="field">
            <label class="field-label">报表名称 <span class="req">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" placeholder="如：本月订单明细" required maxlength="120">
        </div>
        <div class="field">
            <label class="field-label">报表编码 <span class="req">*</span></label>
            <input type="text" name="code" value="{{ old('code') }}" placeholder="如：monthly_orders" required maxlength="60" pattern="[a-z0-9_]+">
            <p class="muted" style="font-size:12px;margin:4px 0 0">小写字母 / 数字 / 下划线，全局唯一</p>
        </div>
        <div class="field">
            <label class="field-label">数据源 <span class="req">*</span></label>
            <select name="data_source" id="dataSource" required>
                <option value="">请选择数据源</option>
                @foreach ($sources as $key => $src)
                    <option value="{{ $key }}" {{ old('data_source') === $key ? 'selected' : '' }}>{{ $src['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="field field-full">
            <label class="field-label">报表说明</label>
            <input type="text" name="description" value="{{ old('description') }}" placeholder="可选" maxlength="255">
        </div>

        <div class="field field-full">
            <label class="field-label">展示列 <span class="req">*</span>（至少勾选一列）</label>
            @foreach ($sources as $key => $src)
                <div class="source-panel" id="panel-{{ $key }}" style="{{ old('data_source') === $key ? '' : 'display:none' }}">
                    <div style="display:flex;flex-wrap:wrap;gap:10px">
                        @foreach ($src['fields'] as $f)
                            <label style="display:flex;align-items:center;gap:5px;font-size:13px;background:var(--line-2);padding:4px 10px;border-radius:6px">
                                <input type="checkbox" name="col_fields[{{ $f['field'] }}]" value="{{ $f['field'] }}|{{ $f['label'] }}"
                                       {{ old('col_fields.'.$f['field']) ? 'checked' : '' }}>
                                {{ $f['label'] }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="field field-full">
            <label class="field-label">筛选项（可选）</label>
            @foreach ($sources as $key => $src)
                <div class="source-panel" id="filter-panel-{{ $key }}" style="{{ old('data_source') === $key ? '' : 'display:none' }}">
                    <div style="display:flex;flex-wrap:wrap;gap:10px">
                        @foreach ($src['filters'] as $f)
                            <label style="display:flex;align-items:center;gap:5px;font-size:13px;background:var(--line-2);padding:4px 10px;border-radius:6px">
                                <input type="checkbox" name="filter_fields[{{ $f['field'] }}]" value="{{ $f['operator'] }}"
                                       {{ old('filter_fields.'.$f['field']) ? 'checked' : '' }}>
                                {{ $f['label'] }}（{{ $f['operator'] === 'eq' ? '精确' : ($f['operator'] === 'contains' ? '包含' : '区间') }}）
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="field field-full">
            <button type="submit" class="btn btn-primary" onclick="return checkColumns()">保存并运行</button>
        </div>
    </form>

    <script>
        const sourceSelect = document.getElementById('dataSource');
        sourceSelect.addEventListener('change', function () {
            document.querySelectorAll('.source-panel').forEach(p => p.style.display = 'none');
            const key = this.value;
            if (key) {
                document.getElementById('panel-' + key).style.display = '';
                document.getElementById('filter-panel-' + key).style.display = '';
            }
        });
        function checkColumns() {
            const key = sourceSelect.value;
            if (!key) return true;
            const panel = document.getElementById('panel-' + key);
            const checked = panel.querySelectorAll('input:checked').length;
            if (checked === 0) {
                alert('请至少勾选一列展示字段');
                return false;
            }
            return true;
        }
    </script>
@endsection
