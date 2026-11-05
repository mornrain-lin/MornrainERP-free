<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * 导出模块清单为 JSON（供 WordPress 插件通过 GitHub API 读取）。
 *
 * 用法：
 *   php artisan modules:export-json
 *   php artisan modules:export-json --out=docs/modules.json
 *
 * 输出结构：
 * {
 *   "generated_at": "2026-10-26 23:50:00",
 *   "version": "v1.x",
 *   "stats": { "total": 58, "ready": 58, "planned": 0 },
 *   "permissions": 29,
 *   "tables": 63,
 *   "groups": [ { "name": "经营概览", "items": [ { "name": "概览", "status": "ready", "permission": "", "route": "dashboard" } ] } ]
 * }
 */
class ExportModulesJson extends Command
{
    protected $signature = 'modules:export-json {--out=docs/modules.json}';

    protected $description = '导出模块清单（config/modules.php）为 JSON，供 WordPress 插件的 GitHub 模块中心读取';

    public function handle(): int
    {
        $groups = [];
        $stats  = ['total' => 0, 'ready' => 0, 'planned' => 0];

        foreach (config('modules', []) as $group) {
            $items = [];
            foreach (($group['items'] ?? []) as $item) {
                $status = $item['status'] ?? 'ready';
                $stats['total']++;
                $stats[$status] = ($stats[$status] ?? 0) + 1;

                $items[] = [
                    'name'       => $item['name'] ?? '',
                    'status'     => $status,
                    'permission' => $item['permission'] ?? '',
                    'route'      => $item['route'] ?? ($item['hub'] ?? ''),
                    'desc'       => $item['desc'] ?? '',
                    'meta'       => $item['meta']['desc'] ?? '',
                ];
            }

            $groups[] = [
                'name'  => $group['title'] ?? ($group['name'] ?? ''),
                'items' => $items,
            ];
        }

        $permissions = collect(config('permissions', []))->flatten()->count();

        $payload = [
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'version'      => config('app.version', 'v1.x'),
            'repo'         => 'mornrain-lin/MornrainERP',
            'stats'        => $stats,
            'permissions'  => $permissions,
            'tables'       => $this->countBusinessTables(),
            'groups'       => $groups,
        ];

        $out = $this->option('out');
        $path = str_starts_with($out, '/') ? $out : base_path($out);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->info("已导出 {$stats['total']} 个模块（ready={$stats['ready']}, planned={$stats['planned']}）→ {$out}");

        return self::SUCCESS;
    }

    private function countBusinessTables(): int
    {
        $tables = \Illuminate\Support\Facades\DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        // 业务表 = 全部表 − 框架表（migrations 等）
        $framework = [
            'migrations', 'failed_jobs', 'password_reset_tokens', 'password_resets',
            'personal_access_tokens', 'jobs', 'job_batches', 'cache', 'cache_locks',
            'sessions', 'telescope_entries',
        ];

        return collect($tables)
            ->pluck('name')
            ->reject(fn ($t) => in_array($t, $framework, true))
            ->count();
    }
}
