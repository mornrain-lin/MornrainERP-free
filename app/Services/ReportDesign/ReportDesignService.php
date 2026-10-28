<?php

namespace App\Services\ReportDesign;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReportTemplate;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Services\Audit\AuditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 报表设计服务
 *
 *  - 预置数据源注册表（白名单模型 + 字段 + 筛选项，杜绝任意 SQL）
 *  - 根据模板构建查询并返回结果集
 *  - 启停 / 删除模板
 */
class ReportDesignService
{
    public function __construct(private AuditService $audit) {}

    /**
     * 数据源注册表：key => [label, model, fields, filters]
     */
    public static function sources(): array
    {
        return [
            'orders' => [
                'label' => '订单',
                'model' => Order::class,
                'fields' => [
                    ['field' => 'order_no', 'label' => '订单号', 'type' => 'text'],
                    ['field' => 'buyer_name', 'label' => '买家', 'type' => 'text'],
                    ['field' => 'buyer_country', 'label' => '国家', 'type' => 'text'],
                    ['field' => 'currency', 'label' => '币种', 'type' => 'text'],
                    ['field' => 'goods_amount', 'label' => '商品金额', 'type' => 'money'],
                    ['field' => 'shipping_income', 'label' => '运费收入', 'type' => 'money'],
                    ['field' => 'discount_amount', 'label' => '优惠金额', 'type' => 'money'],
                    ['field' => 'platform_commission', 'label' => '平台佣金', 'type' => 'money'],
                    ['field' => 'payment_fee', 'label' => '支付手续费', 'type' => 'money'],
                    ['field' => 'refund_amount', 'label' => '退款金额', 'type' => 'money'],
                    ['field' => 'shipping_cost', 'label' => '运费成本', 'type' => 'money'],
                    ['field' => 'ad_cost', 'label' => '广告费', 'type' => 'money'],
                    ['field' => 'other_cost', 'label' => '其他成本', 'type' => 'money'],
                    ['field' => 'status', 'label' => '订单状态', 'type' => 'text'],
                    ['field' => 'source', 'label' => '来源', 'type' => 'text'],
                    ['field' => 'paid_at', 'label' => '付款时间', 'type' => 'datetime'],
                    ['field' => 'created_at', 'label' => '创建时间', 'type' => 'datetime'],
                ],
                'filters' => [
                    ['field' => 'order_no', 'label' => '订单号', 'operator' => 'contains'],
                    ['field' => 'buyer_name', 'label' => '买家', 'operator' => 'contains'],
                    ['field' => 'status', 'label' => '订单状态', 'operator' => 'eq'],
                    ['field' => 'goods_amount', 'label' => '商品金额区间', 'operator' => 'between'],
                    ['field' => 'created_at', 'label' => '创建时间区间', 'operator' => 'between'],
                ],
            ],
            'customers' => [
                'label' => '客户',
                'model' => Customer::class,
                'fields' => [
                    ['field' => 'name', 'label' => '客户名称', 'type' => 'text'],
                    ['field' => 'contact', 'label' => '联系人', 'type' => 'text'],
                    ['field' => 'phone', 'label' => '电话', 'type' => 'text'],
                    ['field' => 'email', 'label' => '邮箱', 'type' => 'text'],
                    ['field' => 'address', 'label' => '地址', 'type' => 'text'],
                    ['field' => 'level', 'label' => '等级', 'type' => 'text'],
                    ['field' => 'is_active', 'label' => '启用', 'type' => 'bool'],
                    ['field' => 'created_at', 'label' => '创建时间', 'type' => 'datetime'],
                ],
                'filters' => [
                    ['field' => 'name', 'label' => '客户名称', 'operator' => 'contains'],
                    ['field' => 'level', 'label' => '等级', 'operator' => 'eq'],
                    ['field' => 'created_at', 'label' => '创建时间区间', 'operator' => 'between'],
                ],
            ],
            'products' => [
                'label' => '商品',
                'model' => Product::class,
                'fields' => [
                    ['field' => 'sku', 'label' => 'SKU', 'type' => 'text'],
                    ['field' => 'name', 'label' => '商品名称', 'type' => 'text'],
                    ['field' => 'category', 'label' => '分类', 'type' => 'text'],
                    ['field' => 'cost_price', 'label' => '成本价', 'type' => 'money'],
                    ['field' => 'stock', 'label' => '库存', 'type' => 'number'],
                    ['field' => 'safety_stock', 'label' => '安全库存', 'type' => 'number'],
                    ['field' => 'is_active', 'label' => '启用', 'type' => 'bool'],
                    ['field' => 'created_at', 'label' => '创建时间', 'type' => 'datetime'],
                ],
                'filters' => [
                    ['field' => 'name', 'label' => '商品名称', 'operator' => 'contains'],
                    ['field' => 'category', 'label' => '分类', 'operator' => 'eq'],
                    ['field' => 'stock', 'label' => '库存区间', 'operator' => 'between'],
                ],
            ],
            'users' => [
                'label' => '账号',
                'model' => User::class,
                'fields' => [
                    ['field' => 'name', 'label' => '姓名', 'type' => 'text'],
                    ['field' => 'email', 'label' => '邮箱', 'type' => 'text'],
                    ['field' => 'role', 'label' => '角色', 'type' => 'text'],
                    ['field' => 'is_active', 'label' => '启用', 'type' => 'bool'],
                    ['field' => 'last_login_at', 'label' => '最近登录', 'type' => 'datetime'],
                    ['field' => 'created_at', 'label' => '创建时间', 'type' => 'datetime'],
                ],
                'filters' => [
                    ['field' => 'name', 'label' => '姓名', 'operator' => 'contains'],
                    ['field' => 'role', 'label' => '角色', 'operator' => 'eq'],
                ],
            ],
            'workflows' => [
                'label' => '流程实例',
                'model' => WorkflowInstance::class,
                'fields' => [
                    ['field' => 'title', 'label' => '标题', 'type' => 'text'],
                    ['field' => 'category', 'label' => '分类', 'type' => 'text'],
                    ['field' => 'workflow_key', 'label' => '流程', 'type' => 'text'],
                    ['field' => 'status', 'label' => '状态', 'type' => 'text'],
                    ['field' => 'current_node', 'label' => '当前节点', 'type' => 'text'],
                    ['field' => 'node_count', 'label' => '节点数', 'type' => 'number'],
                    ['field' => 'created_at', 'label' => '发起时间', 'type' => 'datetime'],
                ],
                'filters' => [
                    ['field' => 'title', 'label' => '标题', 'operator' => 'contains'],
                    ['field' => 'status', 'label' => '状态', 'operator' => 'eq'],
                    ['field' => 'category', 'label' => '分类', 'operator' => 'eq'],
                    ['field' => 'created_at', 'label' => '发起时间区间', 'operator' => 'between'],
                ],
            ],
        ];
    }

    public static function sourceKeys(): array
    {
        return array_keys(self::sources());
    }

    public static function source(string $key): array
    {
        $sources = self::sources();
        if (! isset($sources[$key])) {
            throw new RuntimeException("未知数据源「{$key}」");
        }

        return $sources[$key];
    }

    public function createTemplate(User $user, array $data): ReportTemplate
    {
        return DB::transaction(function () use ($user, $data) {
            $template = ReportTemplate::create([
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'] ?? '',
                'data_source' => $data['data_source'],
                'columns' => $data['columns'],
                'filters' => $data['filters'],
                'created_by' => $user->id,
            ]);
            $this->audit->log('report.design.created', "创建报表「{$template->name}」", $template, $user->id);

            return $template;
        });
    }

    public function updateTemplate(User $user, ReportTemplate $template, array $data): ReportTemplate
    {
        return DB::transaction(function () use ($user, $template, $data) {
            $template->update([
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'] ?? '',
                'data_source' => $data['data_source'],
                'columns' => $data['columns'],
                'filters' => $data['filters'],
            ]);
            $this->audit->log('report.design.updated', "编辑报表「{$template->name}」", $template, $user->id);

            return $template;
        });
    }

    public function toggle(User $user, ReportTemplate $template): ReportTemplate
    {
        return DB::transaction(function () use ($user, $template) {
            $template->update(['is_active' => ! $template->is_active]);
            $this->audit->log('report.design.toggled', ($template->is_active ? '启用' : '停用')."报表「{$template->name}」", $template, $user->id);

            return $template;
        });
    }

    public function deleteTemplate(User $user, ReportTemplate $template): void
    {
        DB::transaction(function () use ($user, $template) {
            $name = $template->name;
            $template->delete();
            $this->audit->log('report.design.deleted', "删除报表「{$name}」", null, $user->id);
        });
    }

    /**
     * 运行报表：按模板列/筛选构建查询，返回行集合。
     */
    public function run(ReportTemplate $template, array $input): Collection
    {
        $source = self::source($template->data_source);
        $model = new $source['model'];

        $query = $model->query();

        foreach ($template->filters as $filter) {
            $field = $filter['field'];
            $operator = $filter['operator'];
            $label = $filter['label'];

            if ($operator === 'eq') {
                $value = $input[$field] ?? null;
                if ($value !== null && $value !== '') {
                    $query->where($field, $value);
                }
            } elseif ($operator === 'contains') {
                $value = $input[$field] ?? null;
                if ($value !== null && $value !== '') {
                    $query->where($field, 'like', '%'.$value.'%');
                }
            } elseif ($operator === 'between') {
                if ($this->isDateField($source, $field)) {
                    $from = $input[$field.'_from'] ?? null;
                    $to = $input[$field.'_to'] ?? null;
                    if ($from && $to) {
                        $query->whereBetween($field, [$from.' 00:00:00', $to.' 23:59:59']);
                    } elseif ($from) {
                        $query->where($field, '>=', $from.' 00:00:00');
                    } elseif ($to) {
                        $query->where($field, '<=', $to.' 23:59:59');
                    }
                } else {
                    $min = $input[$field.'_min'] ?? null;
                    $max = $input[$field.'_max'] ?? null;
                    if ($min !== null && $min !== '' && $max !== null && $max !== '') {
                        $query->whereBetween($field, [(float) $min, (float) $max]);
                    } elseif ($min !== null && $min !== '') {
                        $query->where($field, '>=', (float) $min);
                    } elseif ($max !== null && $max !== '') {
                        $query->where($field, '<=', (float) $max);
                    }
                }
            }
        }

        return $query->orderByDesc('id')->limit(200)->get();
    }

    /**
     * 列值格式化（供视图与导出共用）
     */
    public static function formatValue(mixed $value, string $type): string
    {
        if ($value === null) {
            return '—';
        }

        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        return match ($type) {
            'money' => number_format((float) $value, 2),
            'number' => number_format((float) $value),
            'bool' => $value ? '是' : '否',
            'datetime' => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i') : (string) $value,
            default => (string) $value,
        };
    }

    public static function columnType(string $sourceKey, string $field): string
    {
        $source = self::source($sourceKey);
        foreach ($source['fields'] as $f) {
            if ($f['field'] === $field) {
                return $f['type'];
            }
        }

        return 'text';
    }

    private function isDateField(array $source, string $field): bool
    {
        foreach ($source['fields'] as $f) {
            if ($f['field'] === $field) {
                return $f['type'] === 'datetime';
            }
        }

        return false;
    }
}
