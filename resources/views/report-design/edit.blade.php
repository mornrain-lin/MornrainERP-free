@extends('layouts.app')

@section('title', '设计报表：'.$template->name)
@section('desc', '调整数据源 / 展示列 / 筛选项')

@section('actions')
    <a class="btn" href="{{ route('report-design.show', $template) }}">返回运行</a>
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

    @php
        $selectedColumns = collect($template->columns)->pluck('field')->all();
        $selectedFilters = collect($template->filters)->map(fn ($f) => $f['field'].':'.$f['operator'])->all();
    @endphp

    <form method="POST" action="{{ route('report-design.update', $template) }}" class="form-grid" id="designerForm">
        @csrf
        @method('PUT')

        <div class="field">
            <label class="field-label">报表名称 <span class="req">*</span></label>
            <input type="text" name="name" value="{{ old('name', $template->name) }}" required maxlength="120">
        </div>
        <div class="field">
            <label class="field-label">报表编码 <span class="req">*</span></label>
            <input type="text" name="code" value="{{ old('code', $template->code) }}" required maxlength="60" pattern="[a-z0-9_]+">
            <p class="muted" style="font-size:12px;margin:4px 0 0">小写字母 / 数字 / 下划线，全局唯一</p>
        </div>
        <div class="field">
            <label class="field-label">数据源 <span class="req">*</span></label>
            <select name="data_source" id="dataSource" required>
                @foreach ($sources as $key => $src)
                    <option value="{{ $key }}" {{ old('data_source', $template->data_source) === $key ? 'selected' : '' }}>{{ $src['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="field field-full">
            <label class="field-label">报表说明</label>
            <input type="text" name="description" value="{{ old('description', $template->description) }}" maxlength="255">
        </div>

        <div class="field field-full">
            <label class="field-label">展示列 <span class="req">*</span>（至少勾选一列）</label>
            @foreach ($sources as $key => $src)
                <div class="source-panel" id="panel-{{ $key }}" style="{{ old('data_source', $template->data_source) === $key ? '' : 'display:none' }}">
                    <div style="display:flex;flex-wrap:wrap;gap:10px">
                        @foreach ($src['fields'] as $f)
                            <label style="display:flex;align-items:center;gap:5px;font-size:13px;background:var(--line-2);padding:4px 10px;border-radius:6px">
                                <input type="checkbox" name="col_fields[{{ $f['field'] }}]" value="{{ $f['field'] }}|{{ $f['label'] }}"
                                       {{ in_array($f['field'], $selectedColumns) ? 'checked' : '' }}>
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
                <div class="source-panel" id="filter-panel-{{ $key }}" style="{{ old('data_source', $template->data_source) === $key ? '' : 'display:none' }}">
                    <div style="display:flex;flex-wrap:wrap;gap:10px">
                        @foreach ($src['filters'] as $f)
                            <label style="display:flex;align-items:center;gap:5px;font-size:13px;background:var(--line-2);padding:4px 10px;border-radius:6px">
                                <input type="checkbox" name="filter_fields[{{ $f['field'] }}]" value="{{ $f['operator'] }}"
                                       {{ in_array($f['field'].':'.$f['operator'], $selectedFilters) ? 'checked' : '' }}>
                                {{ $f['label'] }}（{{ $f['operator'] === 'eq' ? '精确' : ($f['operator'] === 'contains' ? '包含' : '区间') }}）
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="field field-full">
            <button type="submit" class="btn btn-primary" onclick="return checkColumns()">保存</button>
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
            const checked = document.getElementById('panel-' + key).querySelectorAll('input:checked').length;
            if (checked === 0) {
                alert('请至少勾选一列展示字段');
                return false;
            }
            return true;
        }
    </script>
@endsection
