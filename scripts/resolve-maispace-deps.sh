#!/usr/bin/env bash
# Resolve private maispace/* Composer packages for standalone extension CI.
# Expects CWD = extension under test; siblings land at ../typo3-extension-*.
# Env: GH_TOKEN (optional, for private GitHub repos)
set -euo pipefail

# actions/checkout keeps a job token that GitHub rejects for every other repo,
# including public ones. Override it on each git invocation instead of editing
# the runner config (unsetting the wrong scope leaves the header in place).
git_public() {
  GIT_TERMINAL_PROMPT=0 command git -c http.https://github.com/.extraheader= "$@"
}

note_failure() {
  local msg="$1"
  msg="${msg//$'\n'/ }"
  msg="$(printf '%s' "${msg}" | sed -E 's#x-access-token:[^@ ]+#x-access-token:***#g' | cut -c1-350)"
  echo "::error::${msg}"
  echo "ERROR: ${msg}" >&2
}

TOKEN="${GH_TOKEN:-${GITHUB_TOKEN:-}}"
AUTH_HEADER=()
if [[ -n "${TOKEN}" ]]; then
  AUTH_HEADER=(-H "Authorization: Bearer ${TOKEN}")
fi

# composer config / path installs may mutate composer.json and composer.lock.
# CI restores both backups after install so `composer validate --strict` matches
# the committed files.
cp composer.json composer.json.ci-bak
if [[ -f composer.lock ]]; then
  cp composer.lock composer.lock.ci-bak
fi

# package name -> source
# source forms:
#   github:org/repo
#   monorepo:packages/typo3-extension-foo  (sparse-checkout from www.bgm-pulheim.org)
declare -A PKG_SOURCE=(
  [maispace/mai-accessibility]="github:mai-space-de/typo3-extension-accessibility"
  [maispace/mai-account]="github:mai-space-de/typo3-extension-account"
  [maispace/mai-alterations]="monorepo:packages/typo3-extension-alterations"
  [maispace/mai-assets]="github:mai-space-de/typo3-extension-assets"
  [maispace/mai-base]="github:mai-space-de/typo3-extension-base"
  [maispace/mai-canteen]="github:mai-space-de/typo3-extension-canteen"
  [maispace/mai-consent]="github:mai-space-de/typo3-extension-consent"
  [maispace/mai-events]="github:mai-space-de/typo3-extension-events"
  [maispace/mai-faq]="github:mai-space-de/typo3-extension-faq"
  [maispace/mai-gallery]="github:mai-space-de/typo3-extension-gallery"
  [maispace/mai-jobs]="github:mai-space-de/typo3-extension-jobs"
  [maispace/mai-locations]="github:mai-space-de/typo3-extension-locations"
  [maispace/mai-mail]="github:mai-space-de/typo3-extension-mail"
  [maispace/mai-make]="github:mai-space-de/typo3-extension-make"
  [maispace/mai-member]="github:mai-space-de/typo3-extension-member"
  [maispace/mai-menus]="monorepo:packages/typo3-extension-menus"
  [maispace/mai-mjml]="github:mai-space-de/typo3-extension-mjml"
  [maispace/mai-news]="github:mai-space-de/typo3-extension-news"
  [maispace/mai-newsletter]="github:mai-space-de/typo3-extension-newsletter"
  [maispace/mai-resource]="github:mai-space-de/typo3-extension-resource"
  [maispace/mai-rights]="monorepo:packages/typo3-extension-rights"
  [maispace/mai-search]="github:mai-space-de/typo3-extension-search"
  [maispace/mai-seeder]="github:mai-space-de/typo3-extension-seeder"
  [maispace/mai-seo]="github:mai-space-de/typo3-extension-seo"
  [maispace/mai-survey]="github:mai-space-de/typo3-extension-survey"
  [maispace/mai-team]="github:mai-space-de/typo3-extension-team"
  [maispace/mai-testimonials]="github:mai-space-de/typo3-extension-testimonials"
  [maispace/mai-theme]="github:mai-space-de/typo3-extension-theme"
  [maispace/mai-timeline]="github:mai-space-de/typo3-extension-timeline"
  [maispace/mai-translate]="github:mai-space-de/typo3-extension-translate"
  [maispace/mai-turbo]="monorepo:packages/typo3-extension-turbo"
)

dir_for_package() {
  local pkg="$1"
  local short="${pkg#maispace/mai-}"
  echo "typo3-extension-${short}"
}

list_maispace_from_composer() {
  local file="$1"
  [[ -f "$file" ]] || return 0
  jq -r '
    ((.require // {}) + (.["require-dev"] // {}))
    | keys[]
    | select(startswith("maispace/"))
  ' "$file"
}

clone_repo() {
  local url="$1"
  local dest="$2"
  local branch="${3:-}"
  local err="" err2=""
  rm -rf "${dest}"
  if [[ -n "${branch}" ]]; then
    if err="$(git_public clone --depth 1 --branch "${branch}" "${url}" "${dest}" 2>&1)"; then
      return 0
    fi
    rm -rf "${dest}"
  fi
  if err2="$(git_public clone --depth 1 "${url}" "${dest}" 2>&1)"; then
    return 0
  fi
  rm -rf "${dest}"
  note_failure "clone failed for ${dest}: ${err} ${err2}"
  return 1
}

clone_github() {
  local repo="$1"
  local dest="$2"
  local public_url="https://github.com/${repo}.git"
  echo "Checking out ${repo} -> ${dest}"
  # Anonymous first. A GITHUB_TOKEN from the calling repo is rejected for every
  # other repo, including public ones, and that 128 hides a working public clone.
  if clone_repo "${public_url}" "${dest}" main; then
    return 0
  fi
  if [[ -z "${TOKEN}" ]]; then
    echo "ERROR: could not clone ${repo}" >&2
    return 1
  fi
  echo "Public clone failed, retrying ${repo} with token"
  if clone_repo "https://x-access-token:${TOKEN}@github.com/${repo}.git" "${dest}" main; then
    return 0
  fi
  echo "ERROR: could not clone ${repo}" >&2
  return 1
}

ensure_monorepo() {
  local mono_root="$1"
  if [[ -d "${mono_root}/.git" ]]; then
    return 0
  fi
  local public_url="https://github.com/mai-space-de/www.bgm-pulheim.org.git"
  echo "Sparse-checking out monorepo branch development for in-tree packages"
  # `main` is an older tree and does not contain packages/typo3-extension-*.
  local err=""
  if ! err="$(git_public clone --depth 1 --branch development --filter=blob:none --sparse "${public_url}" "${mono_root}" 2>&1)"; then
    note_failure "public monorepo clone failed: ${err}"
    rm -rf "${mono_root}"
    if [[ -z "${TOKEN}" ]]; then
      return 1
    fi
    echo "Public monorepo clone failed, retrying with token"
    if ! err="$(git_public clone --depth 1 --branch development --filter=blob:none --sparse \
      "https://x-access-token:${TOKEN}@github.com/mai-space-de/www.bgm-pulheim.org.git" \
      "${mono_root}" 2>&1)"; then
      note_failure "token monorepo clone failed: ${err}"
      rm -rf "${mono_root}"
      return 1
    fi
  fi
}

link_monorepo_package() {
  local rel_path="$1"
  local dest="$2"
  local mono_root
  mono_root="$(cd .. && pwd)/_maispace-monorepo"
  ensure_monorepo "${mono_root}"
  local err=""
  if ! err="$(git_public -C "${mono_root}" sparse-checkout add "${rel_path}" 2>&1)"; then
    note_failure "sparse-checkout ${rel_path} failed: ${err}"
    exit 1
  fi
  if [[ ! -f "${mono_root}/${rel_path}/composer.json" ]]; then
    note_failure "monorepo path has no composer.json: ${rel_path}"
    exit 1
  fi
  ln -sfn "${mono_root}/${rel_path}" "${dest}"
  echo "Linked ${rel_path} -> ${dest}"
}

ensure_package() {
  local pkg="$1"
  local source="${PKG_SOURCE[$pkg]:-}"
  if [[ -z "${source}" ]]; then
    echo "ERROR: no source mapping for ${pkg}" >&2
    exit 1
  fi

  local dir
  dir="$(dir_for_package "${pkg}")"
  local dest
  dest="$(cd .. && pwd)/${dir}"

  if [[ -e "${dest}" ]]; then
    echo "Already present: ${dest}"
  else
    case "${source}" in
      github:*)
        clone_github "${source#github:}" "${dest}"
        ;;
      monorepo:*)
        link_monorepo_package "${source#monorepo:}" "${dest}"
        ;;
      *)
        echo "ERROR: unknown source for ${pkg}: ${source}" >&2
        exit 1
        ;;
    esac
  fi

  # Ensure root composer can resolve the package via path (covers missing repositories)
  local repo_key
  repo_key="$(echo "${pkg}" | tr '/.' '--')"
  composer config "repositories.${repo_key}" path "../${dir}"
}

# Collect transitive closure of maispace/* requirements
declare -A NEEDED=()
QUEUE=()

seed_from_file() {
  local file="$1"
  local pkg
  while IFS= read -r pkg; do
    [[ -z "${pkg}" ]] && continue
    if [[ -z "${NEEDED[$pkg]+x}" ]]; then
      NEEDED[$pkg]=1
      QUEUE+=("${pkg}")
    fi
  done < <(list_maispace_from_composer "${file}")
}

seed_from_file "composer.json"

# Also honour existing path repository declarations (../typo3-extension-*)
if [[ -f composer.json ]]; then
  while IFS= read -r url; do
    [[ -z "${url}" ]] && continue
    base="$(basename "${url}")"
    if [[ "${base}" == typo3-extension-* ]]; then
      short="${base#typo3-extension-}"
      pkg="maispace/mai-${short}"
      if [[ -z "${NEEDED[$pkg]+x}" ]]; then
        NEEDED[$pkg]=1
        QUEUE+=("${pkg}")
      fi
    fi
  done < <(jq -r '(.repositories // [])[] | select(.type=="path") | .url' composer.json)
fi

if [[ ${#QUEUE[@]} -eq 0 ]]; then
  echo "No maispace/* dependencies to resolve"
  exit 0
fi

idx=0
while [[ ${idx} -lt ${#QUEUE[@]} ]]; do
  pkg="${QUEUE[$idx]}"
  idx=$((idx + 1))
  ensure_package "${pkg}"

  dir="$(dir_for_package "${pkg}")"
  dest="$(cd .. && pwd)/${dir}"
  seed_from_file "${dest}/composer.json"
done

# Path packages are typically branch aliases / @dev
composer config minimum-stability dev
composer config prefer-stable true

echo "Resolved maispace packages: ${!NEEDED[*]}"
