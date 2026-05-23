# Maispace CI Workflows

Shared GitHub Actions reusable workflows for Maispace TYPO3 extensions.

## Available Workflows

### `typo3-extension-ci.yml`

Runs linting and tests for TYPO3 extensions.

**Usage in extension repositories:**

```yaml
name: CI

on:
  push:
  pull_request:
    branches: [main, develop]

jobs:
  ci:
    strategy:
      matrix:
        php: ['8.4', '8.5']
      fail-fast: false
    uses: mai-space-de/ci-workflows/.github/workflows/typo3-extension-ci.yml@main
    with:
      php-version: ${{ matrix.php }}
```

**Requirements:**
- Extension must have `composer lint:check` script (editorconfig + phpcs + phpstan + typoscript)
- Extension must have `composer test` script (PHPUnit)

---

## License

GPL-2.0-or-later
