@echo off
rem 晨雨ERP 收费模块安装脚本（付费用户专用）
rem 模式 A（推荐）：私有主库已含 packages\mornrain\erp-premium，无需任何令牌
rem   scripts\install-premium.bat
rem 模式 B：未带 packages\，改用独立私有 VCS 仓库，令牌由环境变量提供
rem   set GITHUB_TOKEN=<你的令牌> && scripts\install-premium.bat
rem 令牌只在本次 composer 鉴权时经 COMPOSER_AUTH 环境变量传入，
rem 不写入仓库、不进命令行参数、不在日志中回显。
setlocal

if exist "packages\mornrain\erp-premium\composer.json" (
  echo 检测到本地私有包源码，使用 path 仓库安装（无需令牌）
  call composer config repositories.erp-premium path ./packages/mornrain/erp-premium
) else if not "%GITHUB_TOKEN%"=="" (
  echo 未发现本地包，改用私有 VCS 仓库
  set COMPOSER_AUTH={"github-oauth":{"github.com":"%GITHUB_TOKEN%"}}
  call composer config repositories.erp-premium vcs https://github.com/mornrain-lin/erp-premium.git
) else (
  echo 错误：未找到 packages\mornrain\erp-premium，且未设置 GITHUB_TOKEN。 1>&2
  echo 请 clone 含 packages\ 的私有主库，或自行在环境变量中提供令牌。 1>&2
  exit /b 1
)

echo 安装 mornrain/erp-premium
call composer require "mornrain/erp-premium:*"

echo 执行迁移
call php artisan migrate --force
call php artisan config:clear

echo 完成。请到「系统 - 激活码管理」粘贴激活码解锁收费模块。
rem 若使用模式 B，可清除本地记录的令牌：composer config --unset github-oauth.github.com
