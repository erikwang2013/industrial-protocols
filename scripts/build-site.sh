#!/usr/bin/env bash
set -euo pipefail

repo="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILDER="${SITE_BUILDER:-$repo/../.site-builder}"

[[ -f "$BUILDER/build.py" ]] || { echo "missing $BUILDER/build.py" >&2; exit 1; }
[[ -f "$repo/docs/site/content.json" ]] || { echo "missing $repo/docs/site/content.json" >&2; exit 1; }

cp "$repo/docs/site/content.json" "$BUILDER/data/industrial-protocols.json"
exec python3 "$BUILDER/build.py" industrial-protocols
