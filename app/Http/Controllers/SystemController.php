<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FinanceFlow;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\RetailSale;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Services\Audit\AuditService;
use App\Services\Quota\QuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * 系统状态（管理员）
 *
 * 只读运行环境与数据规模概览；附带缓存清理操作。
 */
class SystemController extends Controller
{
    public function status(QuotaService $quota)
    {
        // ---- 运行环境 ----
        $env = [
            'PHP 版本' => PHP_VERSION,
            'Laravel 版本' => app()->version(),
            '数据库' => DB::connection()->getDriverName() === 'sqlite' ? 'SQLite（开发）' : 'MySQL（生产）',
            '时区' => config('app.timezone'),
            '内存限制' => ini_get('memory_limit'),
            '调试模式' => config('app.debug') ? '开启（生产请关闭）' : '关闭',
        ];

        // ---- 数据规模 ----
        $tables = [
            'orders' => ['label' => '电商订单', 'count' => Order::count()],
            'products' => ['label' => '商品 SKU', 'count' => Product::count()],
            'customers' => ['label' => '销售客户', 'count' => Customer::count()],
            'suppliers' => ['label' => '供应商', 'count' => Supplier::count()],
            'purchase_orders' => ['label' => '采购单', 'count' => PurchaseOrder::count()],
            'sale_orders' => ['label' => '销售订单', 'count' => SaleOrder::count()],
            'retail_sales' => ['label' => '零售单', 'count' => RetailSale::count()],
            'finance_flows' => ['label' => '财务流水', 'count' => FinanceFlow::count()],
            'workflow_instances' => ['label' => '审批流程', 'count' => WorkflowInstance::count()],
            'users' => ['label' => '系统账号', 'count' => User::count()],
        ];

        // ---- 存储 ----
        $sqlitePath = database_path('database.sqlite');
        $storage = [
            '数据库文件' => file_exists($sqlitePath) ? $this->formatBytes(filesize($sqlitePath)) : '—',
            '日志文件' => $this->formatBytes($this->dirSize(storage_path('logs'))),
            '框架缓存' => $this->formatBytes($this->dirSize(base_path('bootstrap/cache')) + $this->dirSize(storage_path('framework'))),
        ];

        // ---- 套餐额度 ----
        $quotaInfo = [
            'plan' => $quota->planName(),
            'limit' => $quota->monthlyQuota(),
            'used' => $quota->usedThisMonth(),
            'remaining' => $quota->remaining(),
            'pct' => $quota->isLimited() ? min(100, round($quota->usedThisMonth() / $quota->monthlyQuota() * 100)) : 0,
            'limited' => $quota->isLimited(),
        ];

        return view('system.status', compact('env', 'tables', 'storage', 'quotaInfo'));
    }

    /** 清理应用缓存（配置 / 路由 / 视图 / 事件 / 优化） */
    public function clearCache(AuditService $audit)
    {
        try {
            Artisan::call('optimize:clear');
            $audit->log('system.cache_cleared', '清理应用缓存');

            return back()->with('ok', '缓存已清理（配置 / 路由 / 视图 / 事件）');
        } catch (\Throwable $e) {
            return back()->with('err', '清理失败：' . $e->getMessage());
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    /** 目录大小（文件逐个累加，避免 exec） */
    private function dirSize(string $dir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }
        $size = 0;
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            $size += is_file($path) ? (int) filesize($path) : $this->dirSize($path);
        }

        return $size;
    }
}
