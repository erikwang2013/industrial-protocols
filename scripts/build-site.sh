#!/usr/bin/env bash
# Rebuild site/ from docs/site/content.json using the external project site builder.
# The builder normally lives next to this repo as ../.site-builder; set
# SITE_BUILDER=/path/to/.site-builder when it is installed elsewhere.
# When the builder is absent, site/ is kept as-is (it is tracked) and only the
# font patch runs, so deployment and font policy still work.
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
content="$repo/docs/site/content.json"
slug="industrial-protocols"

apply_fonts() {
  for page in index.html en.html 404.html; do
    [[ -f "$repo/site/$page" ]] || { echo "error: missing site/$page" >&2; exit 1; }
  done

  # The builder ships commercial font names; force open-source stacks and fail if any remain.
  while IFS= read -r -d '' page; do
    sed -i \
      -e 's|--font: [^;]*;|--font: "Noto Sans SC", "Noto Sans CJK SC", "Source Han Sans SC", "WenQuanYi Micro Hei", ui-sans-serif, system-ui, sans-serif;|' \
      -e 's|--mono: [^;]*;|--mono: ui-monospace, "Noto Sans Mono", "DejaVu Sans Mono", "Liberation Mono", monospace;|' \
      "$page"
  done < <(find "$repo/site" -name '*.html' -print0)

  if grep -rqE 'PingFang SC|Microsoft YaHei|SFMono-Regular|Menlo|Consolas|Segoe UI' "$repo/site"/*.html; then
    echo "commercial font still present; update the font patch in build-site.sh" >&2
    exit 1
  fi
}

builder="${SITE_BUILDER:-}"
if [[ -z "$builder" ]]; then
  for candidate in "$repo/../.site-builder" "$repo/.site-builder"; do
    if [[ -f "$candidate/build.py" ]]; then
      builder="$(cd "$candidate" && pwd)"
      break
    fi
  done
fi

if [[ -z "$builder" || ! -f "$builder/build.py" ]]; then
  echo "warning: .site-builder/build.py not found; keeping tracked site/ as-is." >&2
  echo "Looked in $repo/../.site-builder and $repo/.site-builder." >&2
  echo "Set SITE_BUILDER=/path/to/.site-builder to regenerate." >&2
  apply_fonts
  exit 0
fi

[[ -f "$content" ]] || { echo "error: missing $content" >&2; exit 1; }
python3 -c 'import json, sys; json.load(open(sys.argv[1]))' "$content"

# The builder reads data/<slug>.json; swap in the committed source and restore
# the previous file on exit so the read-only builder tree stays untouched.
data="$builder/data/$slug.json"
backup=""
if [[ -f "$data" ]]; then
  backup="$(mktemp)"
  cp -p "$data" "$backup"
fi
restore_data() {
  if [[ -n "$backup" ]]; then
    cp -p "$backup" "$data"
    rm -f "$backup"
  else
    rm -f "$data"
  fi
}
trap restore_data EXIT INT TERM

mkdir -p "$builder/data"
cp "$content" "$data"
python3 "$builder/build.py" "$slug"

apply_fonts
