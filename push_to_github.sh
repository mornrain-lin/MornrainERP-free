#!/usr/bin/env bash
# 港湾ERP 免费版 -> GitHub 推送脚本
#
# 前置：先在 https://github.com/new 创建一个 EMPTY 仓库
#       Repository name : gangwanERP-free
#       Description     : 港湾ERP 免费版 — 专注进销存 + 财务的开源一体化企业经营管理平台（Laravel 13 + PHP 8.3）
#       !! 不要勾选 README / .gitignore / license，保持完全空白 !!
#
# 用法：bash push_to_github.sh
set -u

REPO="git@github.com:mornrain-lin/gangwanERP-free.git"
BRANCH="main"

echo "[1/4] checking ssh auth ..."
if ssh -T -o StrictHostKeyChecking=no git@github.com 2>&1 | grep -qi "successfully authenticated\|Hi "; then
  echo "      ssh auth OK"
else
  echo "      WARN: could not verify ssh auth, will try push anyway"
fi

echo "[2/4] scanning for secrets (must be clean) ..."
HIT=$(git grep -nIE 'ghp_[A-Za-z0-9]{10,}|github_pat_[A-Za-z0-9_]{10,}|AKIA[0-9A-Z]{10,}|BEGIN (RSA|OPENSSH|PRIVATE) [A-Z ]*PRIVATE KEY' HEAD || true)
if [ -n "$HIT" ]; then
  echo "      ABORT: possible secret found in tracked files:"
  echo "$HIT" | head -20
  exit 1
fi
echo "      clean"

echo "[3/4] pushing $BRANCH ..."
git push -u "$REPO" "$BRANCH"
RC=$?
if [ $RC -ne 0 ]; then
  echo "      push FAILED (rc=$RC)."
  echo "      If it says 'Repository not found', create the empty repo first:"
  echo "      https://github.com/new  ->  name: gangwanERP-free  ->  Create repository (no README)"
  exit $RC
fi

echo "[4/4] done."
echo "      repo: https://github.com/mornrain-lin/gangwanERP-free"
cat <<'TIPS'

      Recommended repo setup (GitHub web UI):
        Settings  -> Default branch: main
        About     -> Website: https://gangwan123.com
                     Topics: erp, laravel, php, open-source, inventory,
                             accounting, self-hosted, crm, cn
        Releases  -> Draft a new release: tag v1.0.0 (see CHANGELOG.md)
        Security  -> Enable private vulnerability reporting
TIPS
