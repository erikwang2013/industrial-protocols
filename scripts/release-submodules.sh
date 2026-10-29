#!/usr/bin/env bash
# Batch release helper for the 40 submodules.
# Dry-run by default: reports current -> next semver tag for clean submodules on main.
# --apply creates and pushes annotated tags and GitHub releases; it never commits.
set -euo pipefail
cd "$(dirname "$0")/.."

APPLY=0 PART=patch ONLY="" YES=0

usage() {
  cat <<'EOF'
Usage: scripts/release-submodules.sh [options]

  --apply         Create and push tags, then create GitHub releases.
  --part PART     patch (default), minor, or major.
  --only a,b      Only submodules named a and b (basename or packages/ path).
  --yes           Skip the interactive confirmation in --apply mode.
  -h, --help      Show this help.

Without --apply this is a dry-run: dirty or non-main submodules are skipped.
EOF
}

while (($#)); do
  case $1 in
    --apply) APPLY=1 ;;
    --part) PART=${2:?--part requires a value}; shift ;;
    --only) ONLY=${2:?--only requires a value}; shift ;;
    --yes) YES=1 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown option: $1" >&2; usage >&2; exit 1 ;;
  esac
  shift
done

case $PART in patch|minor|major) ;; *) echo "Invalid --part: $PART" >&2; exit 1 ;; esac

if ((APPLY)); then
  command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1 \
    || { echo "ABORT: gh is missing or not authenticated; run 'gh auth login'." >&2; exit 1; }
fi

bump() {
  local maj min pat
  IFS=. read -r maj min pat <<< "${1#v}"
  case $PART in
    major) echo "v$((maj + 1)).0.0" ;;
    minor) echo "v${maj}.$((min + 1)).0" ;;
    patch) echo "v${maj}.${min}.$((pat + 1))" ;;
  esac
}

# Local tags only, so a dry-run never touches the network. A stale clone may
# propose a tag that already exists remotely; the push then fails and names it.
latest_tag() {
  git -C "$1" tag --list 'v*' \
    | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' | sort -V | tail -1 || true
}

# git@host:owner/repo.git and https://host/owner/repo both -> owner/repo
slug() {
  local u=${1%.git} repo rest
  repo=${u##*[/:]}; rest=${u%[/:]*}
  echo "${rest##*[/:]}/${repo}"
}

matches() {
  [[ -z $ONLY ]] && return 0
  [[ ",$ONLY," == *",$1,"* || ",$ONLY," == *",$2,"* ]]
}

fail() { echo "FAILED ($1): $2 -- stopping here; nothing further was changed." >&2; exit 1; }

names=() paths=() next_tags=() repos=()
matched=0 skipped=0

printf '%-20s %-9s %-9s %s\n' MODULE CURRENT NEXT REPO

while IFS= read -r line; do
  path=${line#* }
  name=${path##*/}
  matches "$name" "$path" || continue
  matched=$((matched + 1))

  if ! git -C "$path" rev-parse --git-dir >/dev/null 2>&1; then
    echo "SKIP $name: submodule not initialized" >&2; skipped=$((skipped + 1)); continue
  fi
  branch=$(git -C "$path" rev-parse --abbrev-ref HEAD 2>/dev/null || true)
  if [[ $branch != main ]]; then
    echo "SKIP $name: on branch '$branch', expected main" >&2; skipped=$((skipped + 1)); continue
  fi
  if [[ -n $(git -C "$path" status --porcelain) ]]; then
    echo "SKIP $name: working tree is not clean" >&2; skipped=$((skipped + 1)); continue
  fi
  repo=$(git -C "$path" remote get-url origin 2>/dev/null) \
    || { echo "SKIP $name: no origin remote" >&2; skipped=$((skipped + 1)); continue; }

  cur=$(latest_tag "$path"); cur=${cur:-v0.0.0}
  next=$(bump "$cur")
  repo=$(slug "$repo")

  names+=("$name"); paths+=("$path"); next_tags+=("$next"); repos+=("$repo")
  printf '%-20s %-9s %-9s %s\n' "$name" "$cur" "$next" "$repo"
done < <(git config -f .gitmodules --get-regexp '^submodule\..*\.path$')

if ((matched == 0)); then
  echo "No submodule matched --only '$ONLY'." >&2; exit 1
fi

echo
if ((${#names[@]} == 0)); then
  echo "No eligible submodules; nothing to do."
  exit 0
fi
echo "${#names[@]} eligible, $skipped skipped."

if ((APPLY == 0)); then
  echo "Dry-run only: no tags created, nothing pushed."
  exit 0
fi

echo "Apply plan: create and push tags, then create a GitHub release for each module above."
if ((YES == 0)); then
  read -r -p "Type 'yes' to continue: " answer
  [[ $answer == yes ]] || { echo "Aborted; nothing changed."; exit 1; }
fi

for i in "${!names[@]}"; do
  name=${names[$i]} path=${paths[$i]} tag=${next_tags[$i]} repo=${repos[$i]}
  echo "==> $name: $tag"
  git -C "$path" tag -a "$tag" -m "Release $tag" || fail "$name" "git tag -a $tag"
  git -C "$path" push origin "refs/tags/$tag" || fail "$name" "git push tag $tag"
  gh release create "$tag" --repo "$repo" --generate-notes || fail "$name" "gh release create $tag"
done

echo "Done: ${#names[@]} submodule release(s) published."
