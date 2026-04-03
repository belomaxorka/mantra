<p align="center">
  <strong>Mantra CMS</strong><br>
  Lightweight flat-file Content Management System built with PHP
</p>

<p align="center">
  <a href="https://github.com/belomaxorka/mantra/actions"><img src="https://github.com/belomaxorka/mantra/workflows/CI/badge.svg" alt="CI"></a>
  <a href="https://github.com/belomaxorka/mantra/blob/main/LICENSE"><img src="https://img.shields.io/github/license/belomaxorka/mantra" alt="License"></a>
  <img src="https://img.shields.io/badge/php-%3E%3D8.1-8892BF" alt="PHP 8.1+">
  <img src="https://img.shields.io/badge/dependencies-zero-success" alt="Zero Dependencies">
</p>

---

Mantra is a modern flat-file CMS that requires no database, no Composer, and no external dependencies. All content is stored as JSON or Markdown files. Just upload the files to your server and you're ready to go.

## Features

- **Zero dependencies** -- no Composer, no database, no external libraries needed in production
- **Flat-file storage** -- content stored as JSON/Markdown in `content/`, easy to backup and version control
- **Modular architecture** -- extend functionality through self-contained modules with dependency resolution
- **Hook/event system** -- priority-based pub/sub bus for decoupled module communication
- **Admin panel** -- built-in CRUD scaffolding for pages, posts, users, uploads, and permissions
- **Role-based access control** -- three roles (admin, editor, viewer) with granular per-permission overrides and ownership checks
- **Theme engine** -- template hierarchy with smart fallback (theme > module > core), partials, and hook injection points
- **Multi-language** -- built-in i18n with English and Russian, extensible via language files
- **Atomic file I/O** -- exclusive locking and temp-file-then-rename writes prevent corruption under concurrency
- **Schema migrations** -- lazy, automatic JSON document upgrades on read (no manual scripts needed)
- **PSR-3 logging** -- daily-rotated log files with channel support
- **CSRF protection** -- built-in token generation and verification
- **Markdown support** -- write content in Markdown with frontmatter or JSON

## Requirements

| Requirement | Details |
|---|---|
| PHP | >= 8.1 |
| Extensions | `json`, `session`, `openssl` |
| Web server | Apache with `mod_rewrite` or Nginx |
| File system | Write access to `content/`, `storage/`, `uploads/` |

## Quick Start

### 1. Download

```bash
git clone https://github.com/belomaxorka/mantra.git
cd mantra
```

### 2. Start a dev server

```bash
php -S 127.0.0.1:8000 index.php
```

### 3. Install

Open `http://127.0.0.1:8000/` in your browser. The installer will guide you through creating an admin account and initial configuration.

After installation:

| URL | Description |
|---|---|
| `/` | Public site |
| `/admin` | Admin panel |

### Production deployment

1. Point your web server document root at the project directory (where `index.php` lives).
2. Ensure `content/`, `storage/`, and `uploads/` are writable by the web server.
3. The included `.htaccess` handles URL rewriting and blocks access to sensitive files. For Nginx, configure equivalent rewrite rules.

## Directory Structure

```
mantra/
├── core/                   # Core system classes and helpers
│   ├── classes/            # Application, Database, Router, View, Auth, ...
│   ├── helpers/            # Global helper functions
│   ├── lang/               # Core language files (en, ru)
│   └── schemas/            # Document schema definitions
├── modules/                # Modules (plugins)
│   ├── admin/              # Admin panel (core, always enabled)
│   ├── categories/         # Category management for posts
│   ├── seo/                # Meta tags, Open Graph, breadcrumbs
│   └── analytics/          # Google Analytics, Yandex Metrika
├── themes/                 # Themes
│   └── default/            # Default Bootstrap 5 theme
├── content/                # Flat-file content storage
│   ├── pages/              # Page documents (*.json)
│   ├── posts/              # Post documents (*.json / *.md)
│   ├── users/              # User accounts (*.json)
│   └── settings/           # config.json
├── storage/                # Logs and cache (runtime)
├── uploads/                # User-uploaded files
├── docs/                   # Developer documentation
├── index.php               # Main entry point
└── install.php             # Installer
```

## Modules

Modules are self-contained extensions that live in `modules/<name>/` and are enabled via `config.json`.

### Included modules

| Module | Type | Description |
|---|---|---|
| `admin` | Core | Admin panel with pages, posts, users, uploads, permissions, and settings management |
| `categories` | Feature | Category CRUD and post-to-category associations with public category pages |
| `seo` | Utility | Meta tags, Open Graph, Twitter Cards, breadcrumbs |
| `analytics` | Integration | Google Analytics (gtag) and Yandex Metrika integration |

### Creating a module

```
modules/bookmarks/
├── module.json
├── BookmarksModule.php
├── lang/
│   └── en.php
└── views/
    └── index.php
```

**module.json:**

```json
{
    "name": "bookmarks",
    "version": "1.0.0",
    "description": "Bookmark manager",
    "type": "feature",
    "dependencies": { "admin": ">=1.0" }
}
```

**BookmarksModule.php:**

```php
<?php

class BookmarksModule extends Module
{
    public function init()
    {
        $this->hook('routes.register', array($this, 'registerRoutes'));
    }

    public function registerRoutes($data)
    {
        $data['router']->get('/bookmarks', array($this, 'index'));
        return $data;
    }

    public function index()
    {
        return $this->view('index', array('title' => 'Bookmarks'));
    }
}
```

Enable it by adding `"bookmarks"` to `modules.enabled` in `content/settings/config.json`, or toggle it from the admin Settings panel.

For CRUD admin panels, extend `ContentPanel` -- see [docs/ADMIN_PANELS.md](docs/ADMIN_PANELS.md).

## Hook System

Mantra uses a priority-based event bus. Modules register callbacks; the system fires hooks at key lifecycle points.

```php
// Listen to a hook (in module init)
$this->hook('theme.head', function ($html) {
    return $html . '<link rel="stylesheet" href="/my-style.css">';
});

// Fire a hook (from core or modules)
$result = app()->hooks()->fire('my.hook', $data, $context);
```

### Key hooks

| Hook | Purpose |
|---|---|
| `system.init` | System initialized, modules loaded |
| `routes.register` | Register routes on the router |
| `view.render` | Post-process rendered HTML |
| `theme.head` | Inject into `<head>` |
| `theme.footer` | Add scripts before `</body>` |
| `theme.navigation` | Add items to navigation menu |
| `admin.sidebar` | Add items to admin sidebar |
| `permissions.register` | Register module permissions |

Full reference: [docs/HOOKS.md](docs/HOOKS.md)

## Themes

Themes live in `themes/<name>/` and provide templates, assets, and optional translations.

```
themes/my-theme/
├── theme.json
├── assets/
│   └── css/style.css
├── templates/
│   ├── layout.php          # Main layout wrapper
│   ├── home.php            # Homepage
│   ├── page.php            # Single page
│   ├── post.php            # Single post
│   ├── blog.php            # Blog listing
│   ├── 404.php             # Not found
│   └── partials/
│       ├── sidebar.php
│       └── pagination.php
└── lang/
    └── en.php
```

**Template hierarchy** (resolved in order of specificity):

- Pages: `page-{template}` > `page-{slug}` > `page`
- Posts: `post-{template}` > `post-{category}` > `post-{slug}` > `post`

Themes can override module templates by placing them at `themes/{theme}/templates/partials/{module}/{partial}.php`.

## Configuration

All settings are stored in `content/settings/config.json` and managed through the admin panel or edited directly.

<details>
<summary>Configuration reference</summary>

| Section | Key | Default | Description |
|---|---|---|---|
| `site` | `name` | `Mantra CMS` | Site title |
| `site` | `url` | *(auto-detected)* | Base URL |
| `locale` | `timezone` | `UTC` | PHP timezone |
| `locale` | `default_language` | `en` | UI language (`en`, `ru`) |
| `theme` | `active` | `default` | Active theme directory name |
| `content` | `format` | `json` | Storage format (`json` or `markdown`) |
| `content` | `posts_per_page` | `10` | Posts per page on blog/home |
| `modules` | `enabled` | `["admin"]` | List of enabled module IDs |
| `logging` | `level` | `debug` | Minimum log level (PSR-3) |
| `logging` | `retention_days` | `30` | Auto-delete logs older than N days |
| `security` | `password_hash_algo` | `PASSWORD_DEFAULT` | PHP password hashing algorithm |
| `session` | `lifetime` | `7200` | Session lifetime in seconds |
| `debug` | `enabled` | `true` | Show detailed errors |

</details>

## Proxy / CDN

If Mantra runs behind a reverse proxy (Nginx, Cloudflare, etc.), configure trusted proxies so `client_ip()` resolves the real client IP:

```json
{
    "proxy": {
        "trusted_proxies": ["127.0.0.1", "::1", "10.0.0.0/8", "172.16.0.0/12"]
    }
}
```

## Logging

```php
logger()->info('User logged in', array('user_id' => 42));
logger('security')->warning('Failed login attempt');
```

Logs are written to `storage/logs/` with daily rotation (`{channel}-YYYY-MM-DD.log`). Log level is controlled by `logging.level` in config.

## Development

Dev tools require Composer (end users don't need it):

```bash
composer install

composer test          # Run PHPUnit tests
composer lint          # Check code style (PHP CS Fixer, dry run)
composer fix           # Auto-fix code style
```

## Documentation

| Document | Description |
|---|---|
| [HOOKS.md](docs/HOOKS.md) | Hook system architecture and API reference |
| [ADMIN_PANELS.md](docs/ADMIN_PANELS.md) | Admin panel creation guide and ContentPanel API |
| [VIEWS.md](docs/VIEWS.md) | Template rendering, partials, and theme override system |
| [PAGINATION.md](docs/PAGINATION.md) | Paginator API and integration examples |

## Contributing

Contributions are welcome! Please make sure to:

1. Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style
2. Use [Conventional Commits](https://www.conventionalcommits.org/) for commit messages
3. Add tests for new functionality where applicable
4. Run `composer lint` before submitting a PR

## License

[MIT](LICENSE)
