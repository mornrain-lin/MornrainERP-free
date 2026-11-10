#!/usr/bin/env bash
# 晨雨ERP 收费模块安装脚本（付费用户专用）
#
# 模式 A（推荐）：私有主库已含 packages/mornrain/erp-premium，无需任何令牌
#   ./scripts/install-premium.sh
# 模式 B：未带 packages/，改用独立私有 VCS 仓库，令牌由运行环境提供
#   GITHUB_TOKEN=<你的令牌> ./scripts/install-premium.sh
#
# 令牌只在本次 composer 鉴权时经 COMPOSER_AUTH 环境变量传入，
# 不写入仓库、不进命令行参数、不在日志中回显。
set -euo pipefail

if [ -d "packages/mornrain/erp-premium" ]; then
  echo ">> 检测到本地私有包源码，使用 path 仓库安装（无需令牌）"
  composer config repositories.erp-premium path ./packages/mornrain/erp-premium
elif [ -n "${GITHUB_TOKEN:-}" ]; then
  echo ">> 未发现本地包，改用私有 VCS 仓库"
  COMPOSER_AUTH=$(printf '{"github-oauth":{"github.com":"%s"}}' "$GITHUB_TOKEN")
  export COMPOSER_AUTH
  composer config repositories.erp-premium vcs https://github.com/mornrain-lin/erp-premium.git
else
  echo "错误：未找到 packages/mornrain/erp-premium，且未设置 GITHUB_TOKEN。" >&2
  echo "请 clone 含 packages/ 的私有主库，或自行在环境变量中提供令牌。" >&2
  exit 1
fi

echo ">> 安装 mornrain/erp-premium"
composer require "mornrain/erp-premium:*"

echo ">> 执行迁移"
php artisan migrate --force
php artisan config:clear

echo "完成。请到「系统 → 激活码管理」粘贴激活码解锁收费模块。"
# 若使用模式 B，可清除本地记录的令牌：composer config --unset github-oauth.github.com
