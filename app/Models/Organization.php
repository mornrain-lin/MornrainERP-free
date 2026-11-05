<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 组织架构（多组织核算的核心）。
 *
 * 三级树：集团(group) → 公司(company) → 部门(department)。
 * 采用「物化路径(materialized path)」存储，便于 O(1) 取祖先/后代：
 *   path 包含自身 id，如 "1/3/7" 表示 7 隶属于 3 隶属于 1。
 *   根集团 path = (string)自身 id。
 *
 * 本模型不挂载 BelongsToOrganization 作用域（它本身就是组织维度的定义者）。
 */
class Organization extends Model
{
    use HasFactory;

    public const TYPE_GROUP = 'group';
    public const TYPE_COMPANY = 'company';
    public const TYPE_DEPARTMENT = 'department';

    public const TYPES = [
        self::TYPE_GROUP => '集团',
        self::TYPE_COMPANY => '公司',
        self::TYPE_DEPARTMENT => '部门',
    ];

    protected $fillable = [
        'parent_id', 'type', 'name', 'code', 'level', 'path', 'sort', 'status', 'ownership_pct',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'level' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
        'ownership_pct' => 'float',
    ];

    // ---------------- relations ----------------

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    // ---------------- 类型判断 ----------------

    public function isGroup(): bool
    {
        return $this->type === self::TYPE_GROUP;
    }

    public function isCompany(): bool
    {
        return $this->type === self::TYPE_COMPANY;
    }

    public function isDepartment(): bool
    {
        return $this->type === self::TYPE_DEPARTMENT;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * 集团（终极母公司）对该组织的合并持股比例（%）。
     *
     * = 该组织「被直接母公司持有的比例」，沿「父 → 祖父 → … → 根」逐层连乘，
     *   根集团自身视为 100%（被自己持有），不参与连乘。例如：
     *     集团 → 甲（母公司持股 80%）→ 乙（甲持股 100%）
     *       ⇒ 集团对乙的持股 = 80% × 100% = 80%。
     *   根集团自身祖先为空、且自乘时跳过根 → 返回 100（100% 集团内）。
     * 用于合并报表拆分「归属于母公司」与「少数股东」损益。
     */
    public function groupOwnershipPct(): float
    {
        $pct = 1.0;

        // 完整链：祖先（含根，不含自身）+ 自身
        $chain = $this->ancestorIds();
        $chain[] = $this->id;

        foreach ($chain as $id) {
            $node = ($id === $this->id) ? $this : self::find($id);
            if (! $node) {
                continue;
            }
            // 根集团（无父）视为自身持有 100%，不参与连乘
            if ($node->parent_id === null) {
                continue;
            }
            $pct *= ((float) ($node->ownership_pct ?? 100)) / 100.0;
        }

        return round($pct * 100, 2);
    }

    // ---------------- 树辅助 ----------------

    /** 祖先 id 数组（不含自身），按从根到父排序 */
    public function ancestorIds(): array
    {
        if ($this->path === null || $this->path === '') {
            return [];
        }

        // path 含自身 id，需去掉末位才是纯祖先链
        $ids = array_map('intval', explode('/', $this->path));
        array_pop($ids);

        return $ids;
    }

    /** 祖先组织模型集合（含根，不含自身） */
    public function ancestors(): Builder
    {
        $ids = $this->ancestorIds();

        return self::whereIn('id', $ids)->orderBy('level');
    }

    /** 后代 id（含自身） —— 用于组织切换时「查看子树」 */
    public function descendantIdsWithSelf(): array
    {
        $path = (string) $this->path;

        // 显式分组，避免将来叠加其他 where 时被 orWhere 破坏条件
        return self::where(function ($q) use ($path) {
            $q->where('path', $path)->orWhere('path', 'like', $path.'/%');
        })->pluck('id')->map('intval')->all();
    }

    /** 是否自身或后代（用于校验切换目标合法性、防止组织树成环） */
    public function isSelfOrDescendantOf(int $id): bool
    {
        if ((int) $this->id === $id) {
            return true;
        }

        $target = self::find($id);
        if (! $target || $target->path === null || $target->path === '') {
            return false;
        }

        // 必须带 '/' 边界，否则 "1/30" 会被误判成 "1/3" 的后代
        return str_starts_with((string) $this->path, $target->path.'/');
    }

    /**
     * 计算并设置 path / level（在创建或变更 parent 后调用）。
     * 返回是否发生了变更，供调用方决定是否级联子节点。
     */
    public function computePath(): bool
    {
        $level = 0;
        $path = (string) $this->id;

        if ($this->parent_id) {
            $parent = self::find($this->parent_id);
            if ($parent) {
                $level = $parent->level + 1;
                $path = $parent->path.'/'.$this->id;
            }
        }

        $changed = $this->level !== $level || $this->path !== $path;
        $this->level = $level;
        $this->path = $path;

        return $changed;
    }

    /**
     * 重建整棵树物化路径（数据修复 / 批量改挂 / 批量导入后使用）。
     *
     * 不能按旧的 level 排序——改挂父级后旧 level 已失真。这里按父子依赖
     * 做拓扑迭代：只有父节点算完（或父为悬空）才处理子节点，保证父先于子。
     */
    public static function rebuildAllPaths(): void
    {
        $nodes = self::orderBy('id')->get()->keyBy('id');
        $done = [];
        $guard = 0;
        $limit = $nodes->count() + 1;

        while (count($done) < $nodes->count() && $guard++ < $limit) {
            $progress = false;
            foreach ($nodes as $node) {
                if (isset($done[$node->id])) {
                    continue;
                }
                $pid = $node->parent_id ? (int) $node->parent_id : 0;
                // 父节点存在但尚未重算 → 本轮跳过；父为悬空 id 时 computePath 会按根处理
                if ($pid !== 0 && $nodes->has($pid) && ! isset($done[$pid])) {
                    continue;
                }
                $node->computePath();
                $node->saveQuietly();
                $done[$node->id] = true;
                $progress = true;
            }
            if (! $progress) {
                break; // 存在环，避免死循环；保留已算出的部分
            }
        }
    }

    // ---------------- scopes ----------------

    public function scopeType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 1);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('level')->orderBy('sort')->orderBy('id');
    }
}
