#!/usr/bin/env bash
# 推送规则：
#   1. 获取远程最新版本 tag（纯 semver，忽略 v1.1.0-<name> 形式）
#   2. 增量 bump（默认 patch；支持 minor / major）
#   3. 测试门禁：全部测试通过才允许发布
#   4. 提交并推送 main
#   5. 按最新版本增量创建 tag 并推送，创建 GitHub release
#   6. 按需求取消推送后的应用打包（本脚本不包含任何打包步骤）
set -euo pipefail
cd "$(dirname "$0")/.."

PART="${1:-patch}"
NEXT_MSG="${2:-Release v%s}"

git fetch origin --tags --quiet
LATEST=$(git ls-remote --tags origin \
  | awk -F/ '{print $3}' \
  | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' \
  | sort -V \
  | tail -1)
LATEST="${LATEST:-v0.0.0}"

IFS=. read -r MAJ MIN PAT <<< "${LATEST#v}"
case "$PART" in
  major) MAJ=$((MAJ + 1)); MIN=0; PAT=0 ;;
  minor) MIN=$((MIN + 1)); PAT=0 ;;
  patch) PAT=$((PAT + 1)) ;;
  *) echo "用法: scripts/release.sh [patch|minor|major] [tag消息]"; exit 1 ;;
esac
NEXT="v${MAJ}.${MIN}.${PAT}"

echo "==> 最新版本: ${LATEST}  下一个版本: ${NEXT}"
read -r -p "确认发布 ${NEXT} ? [y/N] " CONFIRM
[[ "${CONFIRM,,}" == "y" ]] || { echo "已取消"; exit 1; }

echo "==> 测试门禁"
vendor/bin/phpunit --no-coverage

echo "==> 提交并推送"
git add -A
git commit -m "release: ${NEXT}" --allow-empty
git push origin main

echo "==> 创建 tag + GitHub release"
git tag -a "${NEXT}" -m "$(printf "${NEXT_MSG}" "${NEXT}")"
git push origin "${NEXT}"

if command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1; then
  gh release create "${NEXT}" --generate-notes \
    || echo "gh release 创建失败，请手动执行: gh release create ${NEXT} --generate-notes"
else
  echo "gh CLI 未认证，tag 已推送。手动创建 release: gh release create ${NEXT} --generate-notes"
fi

echo "==> 发布完成（按要求已取消应用打包）"
