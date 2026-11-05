#!/usr/bin/env bash
# 收费私有包安装脚本（付费用户专用）
# 用法: GITHUB_TOKEN=xxxx ./scripts/install-premium.sh
set -euo pipefail

if [ -z "${GITHUB_TOKEN:-}" ]; then
  echo "错误：请设置 GITHUB_TOKEN（对 mornrain-lin/erp-premium 私有仓库有 read 权限）"
  echo "示例: GITHUB_TOKEN=ghp_xxx ./scripts/install-premium.sh"
  exit 1
fi

echo ">> 配置私有仓库源"
composer config repositories.erp-premium vcs https://github.com/mornrain-lin/erp-premium.git
composer config github-oauth.github.com "$GITHUB_TOKEN"

echo ">> 拉取并安装 mornrain/erp-premium"
composer require "mornrain/erp-premium:*"

echo ">> 执行迁移"
php artisan migrate --force
php artisan config:clear

echo "完成。请到「系统 → 激活码管理」粘贴激活码解锁收费模块。"
# 提示：GITHUB_TOKEN 仅用于本次 composer 鉴权，不会写入仓库；
# 如需清除本地记录的令牌：composer config --unset github-oauth.github.com
