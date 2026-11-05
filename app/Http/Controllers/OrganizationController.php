<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * 组织架构管理（三级：集团/公司/部门）。
 * 仅管理员（permission:org.manage）可维护；不影响业务数据作用域本身，
 * 作用域由 OrgContext + BelongsToOrganization 自动生效。
 */
class OrganizationController extends Controller
{
    /** 合法父级类型约束（保证三级结构） */
    private const PARENT_TYPE = [
        Organization::TYPE_GROUP => null,
        Organization::TYPE_COMPANY => Organization::TYPE_GROUP,
        Organization::TYPE_DEPARTMENT => Organization::TYPE_COMPANY,
    ];

    public function index(): View
    {
        $nodes = Organization::ordered()->get();

        return view('organization.index', compact('nodes'));
    }

    public function create(Request $request): View
    {
        $parentId = $request->integer('parent_id');
        $parent = $parentId ? Organization::find($parentId) : null;
        $type = $this->defaultTypeForParent($parent);

        return view('organization.form', [
            'org' => null,
            'parent' => $parent,
            'type' => $type,
            'candidates' => $this->parentCandidates(null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['parent_id' => $request->input('parent_id') ?: null]);
        $data = $request->validate($this->rules());

        $this->assertHierarchy($data['type'], $data['parent_id'] ?? null);

        $data['code'] = $data['code'] ?: null;
        $org = new Organization();
        $org->fill($data);
        $org->save();
        $org->computePath();
        $org->saveQuietly();

        return redirect()->route('organizations.index')
            ->with('ok', '已创建组织：'.$org->name);
    }

    public function edit(Organization $organization): View
    {
        return view('organization.form', [
            'org' => $organization,
            'parent' => $organization->parent,
            'type' => $organization->type,
            'candidates' => $this->parentCandidates($organization->id),
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        if ($organization->code === 'ROOT') {
            return back()->withErrors(['name' => '默认根组织不可编辑类型/父级']);
        }

        $request->merge(['parent_id' => $request->input('parent_id') ?: null]);
        $data = $request->validate($this->rules($organization->id));

        $this->assertHierarchy($data['type'], $data['parent_id'] ?? null, $organization->id);

        $data['code'] = $data['code'] ?: null;
        $organization->fill($data);
        $organization->save();
        $organization->computePath();
        $organization->saveQuietly();
        // 父级变化后级联修复子树路径
        Organization::rebuildAllPaths();

        return redirect()->route('organizations.index')
            ->with('ok', '已更新组织：'.$organization->name);
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        if ($organization->code === 'ROOT') {
            return back()->withErrors(['name' => '默认根组织不可删除']);
        }
        if ($organization->children()->exists()) {
            return back()->withErrors(['name' => '请先删除或移走其子组织']);
        }

        $organization->delete();

        return redirect()->route('organizations.index')
            ->with('ok', '已删除组织：'.$organization->name);
    }

    /** 修复全部物化路径（数据维护工具） */
    public function rebuild(): RedirectResponse
    {
        Organization::rebuildAllPaths();

        return redirect()->route('organizations.index')
            ->with('ok', '已重建组织架构路径');
    }

    // ---------------- 内部辅助 ----------------

    private function rules(?int $ignoreId = null): array
    {
        $codeRule = Rule::unique('organizations', 'code');
        if ($ignoreId) {
            $codeRule->ignore($ignoreId);
        }

        return [
            'name' => ['required', 'string', 'max:60'],
            'type' => ['required', 'in:'.implode(',', array_keys(Organization::TYPES))],
            'parent_id' => ['nullable', 'exists:organizations,id'],
            'code' => ['nullable', 'string', 'max:40', $codeRule],
            'sort' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:0,1'],
        ];
    }

    private function assertHierarchy(string $type, ?int $parentId, ?int $selfId = null): void
    {
        $requiredParent = self::PARENT_TYPE[$type];

        if ($requiredParent === null) {
            if ($parentId !== null) {
                throw ValidationException::withMessages(['parent_id' => '集团组织不能有上级']);
            }

            return;
        }

        if ($parentId === null) {
            throw ValidationException::withMessages(['parent_id' => '「'.Organization::TYPES[$type].'」必须选择上级']);
        }
        $parent = Organization::findOrFail($parentId);
        if ($parent->type !== $requiredParent) {
            throw ValidationException::withMessages(['parent_id' => '「'.Organization::TYPES[$type].'」的上级必须是'.Organization::TYPES[$requiredParent]]);
        }
        if ($selfId !== null && ($parent->id === $selfId || $parent->isSelfOrDescendantOf($selfId))) {
            throw ValidationException::withMessages(['parent_id' => '不能将组织挂到自身或自己的子级之下']);
        }
    }

    private function defaultTypeForParent(?Organization $parent): string
    {
        if (! $parent) {
            return Organization::TYPE_GROUP;
        }

        return match ($parent->type) {
            Organization::TYPE_GROUP => Organization::TYPE_COMPANY,
            Organization::TYPE_COMPANY => Organization::TYPE_DEPARTMENT,
            default => Organization::TYPE_DEPARTMENT,
        };
    }

    /**
     * 可选的父级列表：集团与（本身可作为父级的）组织全集。
     * 表单里 type 可切换，若按当前 type 过滤会导致切换后选不到合法父级；
     * 因此列出全部候选并标注类型，由 assertHierarchy 保证三级结构正确。
     */
    private function parentCandidates(?int $selfId): array
    {
        $self = $selfId ? Organization::find($selfId) : null;
        $selfPath = $self?->path;

        return Organization::whereIn('type', [Organization::TYPE_GROUP, Organization::TYPE_COMPANY])
            ->ordered()
            ->get()
            ->filter(fn (Organization $o) => $o->id !== $selfId
                && ($selfPath === null || ! str_starts_with($o->path, $selfPath.'/')))
            ->all();
    }

}
