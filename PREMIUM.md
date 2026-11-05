# 晨雨ERP · 免费版 / 收费版 边界与交付说明

本文件说明在**源代码公开（GitHub 开源）**前提下，如何**明确划分免费版与收费版功能边界**，
并保障收费逻辑不被公开仓库暴露。

## 核心结论（先说真话）
> 只要收费逻辑也跑在用户机器上且源码公开，技术层面无法做到"绝对不可破解"。
> 本方案的关键不是"让代码不可读"，而是**让收费代码根本不进入公开仓库**——
> 这样"开源"与"暴露收费逻辑"这两个诉求就不再冲突。

## 架构：模型一（WordPress.org 模式）
- **公开仓库 `mornrain/mornrainerp`（MIT）** 只包含：
  - 免费核心：进销存、基础财务、订单、库存、基础报表等；
  - 授权门禁：`LicenseService` / `EnsurePlan` 中间件 / 激活码管理；
  - **锁定桩**：未安装私有包时，收费菜单项展示为"升级"入口（`premium.locked`），点击进入升级引导页，绝不包含任何收费业务逻辑。
- **收费私有包 `mornrain/erp-premium`（proprietary）** 单独私有仓库分发，含：
  - 14 个收费模块（CRM/PM/OA/ADM/MES/EHR/薪资/考勤/招聘/云售后/论坛/问卷/AI/商城）的
    控制器、服务、模型、视图、迁移；
  - 命名空间 `Mornrain\ErpPremium\`，由 `PremiumServiceProvider` 自动注册路由/视图/迁移。

> 因为收费代码在私有仓库、不在公开仓库，任何人 clone 公开仓库都只能得到免费核心。
> 公开仓库里哪怕被改掉 `EnsurePlan` 中间件，也只能解锁一个"什么都没有"的入口——
> 收费逻辑本身从未公开，破解无从谈起。

## 免费版 / 收费版 功能边界
| 维度 | 免费版（公开） | 收费版（私有包） |
|------|---------------|------------------|
| 核心 ERP | 进销存 / 财务 / 订单 / 库存 / 基础报表 | 含免费全部 |
| 组织 | **三级组织（集团→公司→部门）+ 数据隔离 + 跨组织合并报表 / 内部往来抵消**（免费能力，见下节） | 含免费全部 |
| 企业套件 | 仅"升级"入口 | CRM/PM/OA/ADM/MES/EHR/薪资/考勤/招聘/云售后/论坛/问卷 |
| 智能 / 商城 | — | AI 经营助手、完整商城 + 营销 |
| 运行依赖 | 纯本地、离线 | 需有效授权（远程验签）+ 私有包代码 |
| 交付 | GitHub 开源 | 购买授权后私有 Composer 仓库分发 |

## 多组织核算（免费核心）

多组织核算属于**免费版能力**，代码完全在公开仓库内，不依赖私有包。

**组织模型**：三级树 `集团(group) → 公司(company) → 部门(department)`，用物化路径
`path`（`1/3/7`，含自身 id）存储祖先链，O(1) 取祖先 / 子树。`app/Models/Organization.php`
提供层级判断、祖先、后代与 `rebuildAllPaths()`（按父子依赖拓扑迭代，不依赖旧 level 排序）。

**数据隔离（本迭代已交付）**：
- `organizations` 表 + 默认根组织 `ROOT`（迁移自动播种，旧数据平滑升级）；
- 27 张业务表（财务 / 交易 / 库存 / 往来 / 商品 / 门店 / 平台 / 用户）新增
  `organization_id`，迁移把既有数据回填到 `ROOT`，**升级不丢数据、不中断业务**；
- `App\Models\Traits\BelongsToOrganization`：注册全局作用域按当前组织过滤查询，
  并在 `creating` 事件自动给新建数据打上组织标记；
- `App\Support\OrgContext` + `OrgContextMiddleware`：按「会话选择 > 用户归属 > 根组织」
  解析作用域；管理员 / 持 `org.view_all` 者默认「查看全部」，`org.manage` 可维护组织树；
- 顶栏提供**组织切换器**，切换只影响当前会话的数据可见范围；
- 未执行迁移时 `OrgContext` 自动保持无作用域，保证旧安装升级前行为完全不变。

**合并核算（第二迭代已交付）**：跨组织合并报表、内部往来抵消与集团汇总，
同样属于免费核心。采用「镜像流水 + 显式抵消登记」：登记内部往来会在付款方 /
收款方各生成一笔等额反向流水，合并时仅当两端都在合并范围内才成对剔除。
含期间、币别折算（fail-closed）、权限与合并根范围校验、CSV 导出。
完整口径见 [CONSOLIDATION.md](CONSOLIDATION.md)。

> 设计取舍：把多组织（隔离 + 合并）放进免费核心而非收费版，是因为它属于
> 基础架构与通用财务能力，开源用户也应当能用；真正可售卖的是**行业套件**
> （CRM / PM / MES / EHR / AI / 商城）与后续的集团高级分析、托管服务，
> 而非基础核算能力本身。

## 授权与激活流程
1. 用户安装免费版：`composer install && php artisan migrate && php artisan key:generate`。
2. 菜单中收费项为"升级"态；未安装私有包时点击进入 `premium.locked` 引导页。
3. 付费后获私有仓库令牌，按下方"安装收费私有包"安装 `mornrain/erp-premium`。
4. 安装后在「系统 → 激活码管理」粘贴激活码：
   - 激活码格式 `MRERP1.{EDITION}.{EXPIRES}.{NONCE}.{CHECK}`；
   - **当前实现为本地校验（fail-closed）**：`CHECK` 是 `sha256(EDITION|EXPIRES|NONCE|SECRET)`
     的前 8 位，密钥取自 `LICENSE_SECRET`（未配置时回退 `APP_KEY`）；任何异常一律回落 free；
   - 激活后对应套餐（pro / enterprise）的路由门禁 `EnsurePlan` 通过，模块解锁。
5. **后续增强（尚未实现）**：改为授权服务器上的 Ed25519 / JWT 非对称验签
   （私钥不出服务端），并附加吊销与心跳，可达到纵深防御 L1 的强度。
   本地对称校验无法阻止持有源码者自行签发，因此收费模块的真正防线是
   **L2 收费逻辑离岸**——代码本身从未进入公开仓库。

## 安装收费私有包（付费用户）
令牌**仅在运行时使用**，不要写入仓库或提交到任何公开位置。
```bash
# Linux / macOS
GITHUB_TOKEN=xxxx ./scripts/install-premium.sh

# Windows
set GITHUB_TOKEN=xxxx && scripts\install-premium.bat
```
脚本会：配置私有仓库源 → 用令牌拉取 `mornrain/erp-premium` → `composer require` → 执行迁移。
详见私有包内 `README.md`。

## 纵深防御（不依赖单一手段）
- **L1 远程授权服务器**：私钥不出服务器，JWT 短时效 + 可吊销（见 `config/license.php`、`app/Services/LicenseService.php`）。
- **L2 收费逻辑离岸**：核心代码在私有仓库，公开仓库无收费实现（本文件所述的模型一）。
- **L3 加密模块加载**（可选增强）：对必须在客户端运行的收费代码，可叠加 IonCube / SourceGuardian。
- **L4 完整性自校验**：`LicenseService` 为 fail-closed，异常统一回落免费。
- **L5 法律与商业**：免费版 MIT、收费版专有协议；EULA 明确禁止逆向与转售。

## 当前仓库状态提示
- 收费模块已从 `app/`（控制器/服务/模型）、`resources/views/`、`database/migrations/` **抽离**至
  `packages/mornrain/erp-premium/`（被 `.gitignore` 排除，**不会进入公开提交**）。
- 公开仓库 `routes/web.php` 已移除 14 个收费路由组，仅保留 `premium.locked` 桩。
- `app/Support/Premium.php::installed()` 用于判断私有包是否安装，从而切换菜单锁定态。
- 本文件与 `packages/mornrain/erp-premium/README.md` 需保持同步更新。
