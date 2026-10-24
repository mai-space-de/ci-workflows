# mai-space-de CI workflows

Reusable GitHub Actions for TYPO3 extensions.

## `typo3-extension-ci.yml`

Matrix-friendly reusable workflow (`Lint` + `Test`).

### Maispace path dependencies

Standalone extension checkouts cannot resolve `maispace/*` via monorepo `path`
repositories. Before `composer install`, the workflow:

1. Checks out the extension into `extension/`
2. Clones/links required `maispace/*` packages as siblings (`../typo3-extension-*`)
3. Registers Composer path repositories for those siblings

In-tree-only packages (`mai-turbo`, `mai-rights`, `mai-menus`, `mai-alterations`)
are sparse-checked out from `www.bgm-pulheim.org`.

### Secrets

| Secret | Where | Purpose |
|--------|-------|---------|
| `MAI_PACKAGE_TOKEN` | caller repo/org | PAT (or GitHub App) with `contents:read` on private `mai-space-de/*` package repos and the monorepo. Mapped to workflow secret `package-token`. |

If unset, the workflow falls back to `github.token` (works for public package repos only).

Caller example:

```yaml
jobs:
  ci:
    uses: mai-space-de/ci-workflows/.github/workflows/typo3-extension-ci.yml@main
    with:
      php-version: '8.5'
    secrets:
      package-token: ${{ secrets.MAI_PACKAGE_TOKEN }}
```

### PHP memory

`setup-php` sets `memory_limit=1G` so PHPStan does not OOM on the default 128M CI limit.
