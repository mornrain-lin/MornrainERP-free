=== 晨雨ERP — 统一企业经营管理插件 ===
Contributors: mornrain-lin
Tags: erp, inventory, order, purchase, finance, crm, 进销存
Requires at least: 5.9
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

晨雨ERP（MornrainERP）WordPress 统一插件：ERP工具（tool 文章类型 + 使用场景分类）、商品库存、订单、采购、客户供应商、财务资金、经营报表、前台卖家工具台，并内置 GitHub 模块中心。

== Description ==

把「ERP工具」与「晨雨ERP」整合为一个独立插件（原为主题内嵌模块），并打通 GitHub：后台可直接查看仓库模块清单与最近提交。

**业务能力**
* 商品管理：SKU / 分类 / 售价 / 成本 / 安全库存
* 库存：多仓分仓、出入库流水、其他出入库、仓库调拨、库存盘点（库存变动唯一入口）
* 订单：订单 + 明细，利润 = 营收 − 成本 − 平台费 自动核算；发货自动出库（幂等）
* 采购：采购单 + 明细，入库自动回补库存（幂等）
* 往来单位：客户 / 供应商档案
* 财务：资金账户、收支流水、应收未收、应付未付
* 报表：月度利润、近 12 月趋势、渠道贡献、CSV 导出
* 看板：KPI + 经营预警 + 低库存提醒

**GitHub 模块中心**
* 只读调用 GitHub REST API（仓库信息 / 最近提交 / 模块清单）
* 数据源：docs/modules.json → config/modules.php → 内置快照
* 12 小时缓存 + 手动刷新；支持可选 Token（匿名 60 次/小时，Token 5000 次/小时）
* Token 仅存本站数据库或 wp-config.php 常量，界面永不回显明文

**短代码**
* `[gangwan_erp]` 前台卖家工具台
* `[gangwan_erp_modules]` 前台模块清单

**其它**
* 纯 PHP + $wpdb，零外部依赖、零 CDN
* 激活时自动接管主题遗留 cerp_* 数据（仅一次，旧表保留）
* 颜色遵循中国习惯：盈利/涨=红，亏损/跌=绿

== Installation ==

1. 上传 `gangwan-erp` 目录到 `/wp-content/plugins/`，或在后台「插件 → 安装插件」上传 zip。
2. 后台启用「晨雨ERP」。
3. 若站点使用 gangwan-erp 主题，请把主题 `functions.php` 里对 `inc/cpt.php` 与 `inc/erp.php` 的 require 改为：
   `if ( ! defined( 'GW_ERP_VERSION' ) ) { require_once ...; }`
4. 进入「晨雨ERP → 设置」配置 GitHub 仓库（默认 mornrain-lin/MornrainERP）与低库存阈值。

== Frequently Asked Questions ==

= 会丢失主题里已有的 ERP 数据吗？ =
不会。激活时若发现 `{prefix}cerp_*` 表且新表为空，会自动迁移商品/仓库/库存/订单/采购数据，旧表保留不动。

= 模块中心显示「配额已用尽」怎么办？ =
匿名访问为 60 次/小时，结果本身缓存 12 小时。可在设置里填写 GitHub Token，或在 wp-config.php 定义 `GW_ERP_GITHUB_TOKEN`。

= 主题停用后还能用吗？ =
插件自带完整的 tool CPT 与 scenario 分类法，不依赖主题；但前台展示模板（archive-tool.php 等）由主题提供，需要自行补模板或保留主题。

== Changelog ==

= 1.0.0 =
* 整合「ERP工具」CPT 与「晨雨ERP」业务模块为统一插件
* 新增库存流水 / 调拨 / 盘点 / 往来单位 / 资金账户 / 收支流水 / 应收应付 / 经营报表
* 新增 GitHub 模块中心（API 只读 + 12 小时缓存 + 可选 Token）
* 新增 CSV 导出（订单 / 商品 / 库存 / 流水 / 采购）
* 兼容层保留主题旧函数名，主题模板零改动
