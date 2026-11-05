# 晨雨ERP — WordPress 统一插件

把原来分散在两处的 ERP 能力整合为**一个独立插件**：

| 合并前 | 位置 | 合并后 |
|---|---|---|
| **ERP工具**（`tool` 文章类型 + `scenario` 使用场景分类 + 15 个工具元字段） | 主题 `inc/cpt.php` | 插件 `includes/class-gw-erp-cpt.php` |
| **晨雨ERP**（商品 / 库存 / 订单 / 采购 / 看板 / 前台卖家工具台，`cerp_*` 表） | 主题 `inc/erp/*` | 插件 `includes/class-gw-erp-db.php` 等，`gw_*` 表 |
| —（新增） | — | 库存流水、仓库调拨、盘点、客户/供应商、资金账户与收支流水、应收应付、经营报表、CSV 导出 |
| —（新增） | — | **GitHub 模块中心**：后台直接查看 GitHub 仓库模块清单与最近提交 |

- 版本：1.0.0 ｜ 要求：WordPress 5.9+ / PHP 7.4+
- 纯 PHP + `$wpdb`，**零外部依赖、零 CDN**
- 金额口径：人民币，`DECIMAL(12,2)`；颜色遵循中国习惯（盈利/涨=红，亏损/跌=绿）

## 一、目录结构

```
gangwan-erp/
├── gangwan-erp.php                 # 插件头 + 常量 + 自动加载 + 生命周期
├── uninstall.php                   # 卸载清理（可用 GW_ERP_KEEP_DATA 保留数据）
├── includes/
│   ├── class-gw-erp-install.php    # 建表 / 升级 / 接管 cerp_ 旧数据 / 示例数据 / 重置
│   ├── class-gw-erp-db.php         # 数据访问层：CRUD + 库存唯一入口 + 利润核算 + 财务
│   ├── class-gw-erp-dashboard.php  # KPI / 预警 / CSV 导出
│   ├── class-gw-erp-cpt.php        # ERP工具(tool) + 使用场景(scenario) + 元字段
│   ├── class-gw-erp-admin.php      # 后台菜单 / 路由 / 表单处理 / 导出 / 重置
│   ├── class-gw-erp-github.php     # GitHub API 客户端（仓库 / 提交 / 模块清单）
│   ├── class-gw-erp-modules.php    # GitHub 模块中心页面
│   ├── class-gw-erp-shortcode.php  # [gangwan_erp] / [gangwan_erp_modules]
│   └── compat-legacy.php           # 主题遗留 gangwan_erp_* 函数兼容层
├── views/                          # 9 个后台页面模板
├── assets/css/{admin,front}.css    # 本地样式（无 CDN）
├── assets/js/admin.js              # 明细行增删
└── data/modules-snapshot.json      # 离线兜底快照（GitHub 不可达时展示）
```

## 二、数据模型（`{prefix}gw_*`，11 张表）

`products` `warehouses` `inventory` `stock_movements` `partners` `orders`
`order_items` `purchases` `purchase_items` `finance_accounts` `finance_flows`

三条硬约定：

1. **库存变动唯一入口** `GW_ERP_DB::move_stock()` —— 改库存必然写 `stock_movements`，禁止直接改 `inventory`；
2. **利润唯一入口** `GW_ERP_DB::save_order()` —— `利润 = 营收 − 成本 − 平台费` 落库，不在视图里另算；
3. **资金唯一入口** `GW_ERP_DB::record_flow()` —— 改余额与写流水在同一事务内。

## 三、与主题的关系（接管而非破坏）

插件启用后，主题自带的 `inc/cpt.php` 与 `inc/erp/*` 应停止加载，避免函数重复声明。
推荐在主题 `functions.php` 里改成条件加载：

```php
if ( ! defined( 'GW_ERP_VERSION' ) ) {
    require_once get_template_directory() . '/inc/cpt.php';
    require_once get_template_directory() . '/inc/erp.php';
}
```

插件通过 `includes/compat-legacy.php` 保留了全部旧函数名（`gangwan_erp_tbl()`、
`gangwan_erp_kpis()`、`gangwan_meta_rows()` 等），因此主题的
`archive-tool.php` / `single-tool.php` / `taxonomy-scenario.php` / 短代码
**无需任何改动即可继续工作**。万一插件被停用，主题会自动回退到自带实现。

### 旧数据接管

激活时若发现主题遗留 `{prefix}cerp_*` 表且新表为空，自动迁移商品 / 仓库 / 库存 /
订单（含明细）/ 采购（含明细），并按现有库存补一条「期初库存」流水。
迁移只做一次（记录在 `gw_erp_cerp_migrated`），旧表保留不动，可随时回退。

## 四、后台功能

| 菜单 | 能力 |
|---|---|
| 概览看板 | 8 项 KPI、经营预警、近期订单、低库存、渠道分布 |
| 商品管理 | 增删改查、搜索、安全库存、CSV 导出 |
| 库存与流水 | 分仓库存、其他出入库、仓库调拨、盘点、流水（可按类型筛选） |
| 订单管理 | 订单 + 明细、利润自动核算、置「已发货/已完成」自动出库（幂等） |
| 采购管理 | 采购单 + 明细、置「已入库」自动入库（幂等）、供应商联想 |
| 客户与供应商 | 往来单位档案 |
| 财务资金 | 资金账户、收支流水（改余额+写流水）、应收未收、应付未付 |
| 经营报表 | 月度利润、近 12 月趋势、渠道贡献、5 类 CSV 导出 |
| GitHub 模块中心 | 仓库信息 + 模块清单 + 最近提交（见下） |
| 设置 | 低库存阈值 / 货币符号 / 工具台可见角色 / GitHub 连接 / 数据重置 |

## 五、GitHub 模块中心（API 打通）

- **数据源优先级**：`docs/modules.json`（仓库用 `php artisan modules:export-json` 生成）
  → 解析 `config/modules.php`（尽力而为）→ 插件内置快照。
- **只读**调用 GitHub REST：`/repos/{owner}/{repo}`、`/commits?per_page=5`、`/contents/...`。
- **缓存**：transient 12 小时；后台「立即刷新」按钮（`admin_post_gw_erp_refresh_modules`）强制拉取。
- **鉴权（可选）**：匿名 60 次/小时；可在设置页填 Token（5000 次/小时），
  或在 `wp-config.php` 里 `define( 'GW_ERP_GITHUB_TOKEN', '…' );`（优先）。
  Token 只存数据库/常量，界面仅显示占位，**永不回显明文**。
- **限流兜底**：403 rate limit 会给出中文提示并回退到缓存/快照，不会白屏。

## 六、短代码

| 短代码 | 说明 |
|---|---|
| `[gangwan_erp]` | 前台卖家工具台（KPI + 近期订单），参数 `limit` |
| `[gangwan_erp_modules]` | 前台展示 GitHub 模块清单，参数 `group="分组名"` |

## 七、权限

默认 `manage_options`（管理员）。可用过滤器放宽到自定义能力：

```php
add_filter( 'gw_erp_capability', fn() => 'edit_posts' );
```

## 八、安全

- 所有写操作走 `admin-post.php` + `check_admin_referer()` nonce + 能力校验；
- 所有 SQL 走 `$wpdb->prepare()`；输出统一 `esc_html()/esc_attr()/esc_url()`；
- 删除类操作带 JS 二次确认；重置数据需输入 `RESET`；
- 卸载可用 `GW_ERP_KEEP_DATA` 保留业务数据。
