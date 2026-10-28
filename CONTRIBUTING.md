# 贡献指南

感谢你愿意为港湾ERP 免费版出一份力。本项目采用 **Open Core** 模式：
核心业务主线 MIT 开源永久免费，收入来自托管与增值服务。你的每一份贡献都会直接让免费版变得更好。

参与本项目即视为接受 [行为准则](CODE_OF_CONDUCT.md)。

---

## 一、如何提需求 / 报 Bug

1. 先搜索 [已有 Issue](https://github.com/mornrain-lin/gangwanERP-free/issues)，避免重复。
2. Bug 用 **Bug 报告模板**，需求用 **功能建议模板**，把「复现步骤 / 期望结果」写清楚。
3. **安全漏洞不要开公开 Issue**，请按 [SECURITY.md](SECURITY.md) 私下报告。

---

## 二、开发流程

```bash
# 1. Fork 本仓库后克隆
git clone https://github.com/<你的账号>/gangwanERP-free.git
cd gangwanERP-free

# 2. 建分支（命名见下表）
git checkout -b feat/stock-batch-import

# 3. 本地跑起来
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --force --seed
php artisan serve

# 4. 提交（见提交规范）
git commit -m "feat(warehouse): 支持按批次导入库存"

# 5. 推送并发 PR 到 main
git push origin feat/stock-batch-import
```

### 分支命名

| 前缀 | 用途 |
|---|---|
| `feat/` | 新功能 |
| `fix/` | 缺陷修复 |
| `refactor/` | 重构（不改行为） |
| `docs/` | 文档 |
| `chore/` | 依赖、脚本、CI |

### 提交信息规范

采用 Conventional Commits，中文描述即可：

```
<type>(<scope>): <简述>

type:  feat | fix | refactor | docs | chore | test | perf
scope: warehouse | finance | sales | retail | purchase | report | system | workflow | platform | wp
```

示例：

```
feat(finance): 账务台账支持按往来单位筛选
fix(warehouse): 调拨单审核后目的仓库存未增加
```

---

## 三、提交前自检清单

PR 前请确认全部通过：

- [ ] `php -l` 无语法错误（CI 会全量检查 `app/ config/ routes/ database/`）
- [ ] 改动涉及金额时，明确字段属于**平台币**还是**人民币**，不混用
- [ ] 库存增减走 `App\Services\Inventory\StockService`，没有直接改 `products.stock`
- [ ] 资金变动走 `App\Services\Finance\FinanceService::record()`
- [ ] 利润计算走 `App\Support\ProfitCalculator`，没有在控制器 / 视图里另写公式
- [ ] 新增菜单只改 `config/modules.php`，且 `permission` 与路由中间件成对出现
- [ ] 新增权限点已登记 `config/permissions.php`，并重跑 `RolePermissionSeeder`
- [ ] **不引入任何外部 CDN 与前端构建链**（CSS / JS 一律放 `public/`）
- [ ] 数据库变更走 migration（可回滚），不手改已有迁移文件
- [ ] 若改动模块清单，重新生成快照：`php artisan modules:export-json`
- [ ] 敏感信息（密钥、口令、token）未进入任何文件

---

## 四、代码风格

- PHP 遵循 PSR-12，可用 `composer pint`（`./vendor/bin/pint --test`）自检
- 类名、方法名英文；界面文案、注释中文
- 控制器保持薄：业务逻辑下沉到 `app/Services/`
- 列表页避免 N+1：用 `withSum` / `with` 预聚合
- 视图不写业务公式，只做展示

---

## 五、PR 评审

- 一个 PR 只做一件事，描述里写清「改了什么 / 为什么改 / 怎么验证」
- 涉及界面变动，请附截图
- 涉及数据库变更，请在描述里注明迁移文件名与是否可回滚
- 维护者会尽量在 3 个工作日内回应；若需修改会直接留言

---

## 六、许可

你提交的代码默认以本项目相同的 **MIT License** 授权，并同意被包含在发行版中。
