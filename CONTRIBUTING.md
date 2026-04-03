# Contributing to Mantra CMS

Thank you for considering contributing to Mantra CMS! This guide explains how to set up your development environment, run tests, and submit changes.

## Getting Started

### Prerequisites

- PHP >= 8.1 with extensions: `json`, `session`, `openssl`, `mbstring`
- Composer (dev dependencies only)
- Git

### Setup

```bash
git clone https://github.com/belomaxorka/mantra.git
cd mantra
composer install
```

### Run locally

```bash
php -S 127.0.0.1:8000 index.php
```

Open `http://127.0.0.1:8000/install.php` on first run to create an admin account.

### Run tests

```bash
composer test        # PHPUnit
composer lint        # code style check (dry-run)
composer fix         # auto-fix code style
```

Tests must pass on PHP 8.1 through 8.5. CI runs the full matrix automatically.

## Zero Production Dependencies

Mantra has **no runtime dependencies** — end users never run `composer install`. Composer is only used for dev tooling (PHPUnit, php-cs-fixer). Any new dependency must be dev-only and listed in `.gitattributes` `export-ignore`.

Do not introduce Composer packages that would need to ship to production.

## Code Style

The project uses [PHP-CS-Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) with the config in `.php-cs-fixer.php`. Key rules:

- `@PHP81Migration` baseline
- Short array syntax (`[]` over `array()`)
- Trailing commas in multiline arrays, arguments, parameters
- No unused imports
- Single space around binary operators and concatenation

CI auto-fixes and commits style violations on push, but please run `composer fix` locally before committing to keep the history clean.

## Commit Conventions

We use [Conventional Commits](https://www.conventionalcommits.org/):

```
type(scope): short summary

Longer description of why the change was made and what it affects.
```

### Types

| Type | When to use |
|------|-------------|
| `feat` | New feature or capability |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `style` | Code formatting (no logic change) |
| `refactor` | Code restructuring (no behavior change) |
| `perf` | Performance improvement |
| `test` | Adding or updating tests |
| `ci` | CI/CD pipeline changes |
| `chore` | Maintenance (deps, configs, tooling) |

### Scopes

Use the component name: `admin`, `router`, `view`, `hooks`, `database`, `auth`, `modules`, `i18n`, `url`, `install`, `tools`, etc.

### Examples

```
feat(modules): add settings schema validation on save
fix(url): normalize base_url slashes
docs(tools): add documentation for seed and bench utilities
test: add DatabaseSchema edge-case tests
ci: add PHP 8.5 to test matrix
```

Always include a **body** explaining *why* the change was made, not just *what* changed.

## Branching and Pull Requests

1. Create a branch from `main`:
   ```bash
   git checkout -b feat/my-feature
   ```

2. Make your changes in small, focused commits.

3. Make sure tests pass:
   ```bash
   composer fix && composer test
   ```

4. Push and open a pull request against `main`.

5. Fill in the PR template — describe the change, link related issues, and complete the checklist.

### PR Guidelines

- **One concern per PR.** A bug fix and a new feature should be separate PRs.
- **Keep PRs small.** Large PRs are harder to review and more likely to stall.
- **Update tests.** If you change behavior, update or add tests to cover it.
- **Don't refactor while fixing.** Style changes mixed with logic changes make review difficult.
- **No generated files.** Don't commit IDE configs, OS files, or build artifacts.

## Project Architecture

See [CLAUDE.md](CLAUDE.md) for a detailed architecture overview including:

- Entry points and bootstrap sequence
- Application lifecycle
- Module system and hook bus
- Database, routing, auth, views
- Admin panel framework

Key docs in `docs/`:

| Document | Description |
|----------|-------------|
| [HOOKS.md](docs/HOOKS.md) | Hook system reference |
| [ADMIN_PANELS.md](docs/ADMIN_PANELS.md) | Admin panel creation guide |
| [VIEWS.md](docs/VIEWS.md) | Template and partial system |
| [PAGINATION.md](docs/PAGINATION.md) | Paginator API |

## Reporting Bugs

Use the [Bug Report](https://github.com/belomaxorka/mantra/issues/new?template=bug_report.yml) issue template. Include:

- Steps to reproduce
- Expected vs actual behavior
- PHP version and OS
- Relevant logs from `storage/logs/`

## Requesting Features

Use the [Feature Request](https://github.com/belomaxorka/mantra/issues/new?template=feature_request.yml) issue template. Describe the problem you're solving, not just the solution you want.

## Security Vulnerabilities

**Do not open a public issue for security vulnerabilities.** See [SECURITY.md](SECURITY.md) for responsible disclosure instructions.

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE).
