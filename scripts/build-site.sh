#!/usr/bin/env bash
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILDER="${SITE_BUILDER:-$repo/../.site-builder}"

[[ -f "$BUILDER/build.py" ]] || { echo "missing $BUILDER/build.py" >&2; exit 1; }
[[ -f "$repo/docs/site/content.json" ]] || { echo "missing $repo/docs/site/content.json" >&2; exit 1; }

cp "$repo/docs/site/content.json" "$BUILDER/data/industrial-protocols.json"
python3 "$BUILDER/build.py" industrial-protocols

# The builder ships commercial font names; force open-source stacks and fail if any remain.
find "$repo/site" -name '*.html' -print0 | xargs -0 -r sed -i \
  -e 's|--font: [^;]*;|--font: "Noto Sans SC", "Noto Sans CJK SC", "Source Han Sans SC", "WenQuanYi Micro Hei", ui-sans-serif, system-ui, sans-serif;|' \
  -e 's|--mono: [^;]*;|--mono: ui-monospace, "Noto Sans Mono", "DejaVu Sans Mono", "Liberation Mono", monospace;|'

if grep -rqE 'PingFang SC|Microsoft YaHei|SFMono-Regular|Menlo|Consolas|Segoe UI' "$repo/site"/*.html; then
  echo "commercial font still present; update the font patch in build-site.sh" >&2
  exit 1
fi
