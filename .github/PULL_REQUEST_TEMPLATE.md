## 这个 PR 做了什么

<!-- 一句话说明改动，必要时说明为什么改 -->

## 类型

- [ ] feat 新功能
- [ ] fix 缺陷修复
- [ ] refactor 重构（不改行为）
- [ ] docs 文档
- [ ] chore 依赖 / 脚本 / CI

## 影响模块

<!-- 例如：仓库管理 / 财务管理 / WordPress 插件 -->

## 自检清单

- [ ] `php -l` 无语法错误
- [ ] 金额字段口径明确（平台币 / 人民币未混用）
- [ ] 库存变动走 `StockService`，资金变动走 `FinanceService`
- [ ] 利润计算走 `ProfitCalculator`
- [ ] 新增菜单只改 `config/modules.php`，`permission` 与路由中间件成对
- [ ] 新增权限点已登记 `config/permissions.php` 并重跑 `RolePermissionSeeder`
- [ ] 未引入外部 CDN 与前端构建链
- [ ] 数据库变更走 migration 且可回滚
- [ ] 模块清单有变动时已重跑 `php artisan modules:export-json`
- [ ] 未提交任何密钥、口令、token

## 如何验证

<!-- 复现步骤 / 测试命令；界面改动请附截图 -->

```bash
php artisan migrate --force --seed
php artisan serve
```

## 相关 Issue

<!-- 例如：Closes #12 -->
