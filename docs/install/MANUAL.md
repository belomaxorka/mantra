# Manual installation

## Requirements

- PHP 8.1.0 or newer
- Web server: Apache 2.4+ or Nginx

### Required PHP extensions

| Extension | Purpose |
|---|---|
| `json` | Flat-file database (built-in since PHP 8.0) |
| `session` | Authentication |
| `openssl` | CSRF tokens, password hashing |

### Recommended PHP extensions

| Extension | Purpose |
|---|---|
| `fileinfo` | MIME type validation for uploads |
| `iconv` | Slug generation from non-Latin characters |
| `zlib` | Gzip output compression |

The installer checks for required extensions on startup and will report any that are missing.

## Installation

### 1. Upload files

Extract the release archive into the web server's document root or a subdirectory:

```
/var/www/example.com/
├── core/
├── modules/
├── themes/
├── content/
├── storage/
├── uploads/
├── index.php
├── install.php
└── .htaccess
```

### 2. Set directory permissions

The web server process must have write access to three directories:

```bash
chown -R www-data:www-data content/ storage/ uploads/
chmod -R 755 content/ storage/ uploads/
```

Replace `www-data` with the user your web server runs as (e.g. `nginx`, `apache`, `http`).

### 3. Configure the web server

#### Apache

The `.htaccess` file included in the project handles everything automatically. Make sure:

- `mod_rewrite` is enabled: `a2enmod rewrite`
- `AllowOverride All` is set for the site directory

Example virtual host:

```apache
<VirtualHost *:80>
    ServerName example.com
    DocumentRoot /var/www/example.com

    <Directory /var/www/example.com>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx

Nginx does not use `.htaccess`. Add the following rules to the `server` block:

```nginx
server {
    listen 80;
    server_name example.com;
    root /var/www/example.com;
    index index.php;

    charset utf-8;

    # Block hidden files and directories (.git, .env, etc.)
    location ~ /\. {
        return 404;
    }

    # Block sensitive directories
    location ^~ /content/ { return 404; }
    location ^~ /storage/ { return 404; }

    # Block core except assets
    location ^~ /core/ {
        location ^~ /core/assets/ {
            # allow
        }
        return 404;
    }

    # Block module language files
    location ~ ^/modules/.*/lang/ { return 404; }

    # Block sensitive file extensions
    location ~ \.(json|md|lock|bak|sql|tmp|dist|log)$ { return 404; }

    # Route all other requests through index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Adjust `fastcgi_pass` to match your PHP-FPM socket or TCP address (e.g. `127.0.0.1:9000`).

### 4. Run the installer

Open `http://example.com/install.php` in your browser.

Fill in the form:

- **Site Name** — displayed in the header and page title
- **Language** — English or Russian
- **Admin Username** — 3-32 characters (letters, numbers, hyphens, underscores)
- **Admin Password** — 6 characters minimum

After submitting, the installer creates all necessary directories, writes the configuration to `content/settings/config.json`, and creates the admin user.

### 5. Done

- `http://example.com/` — the site
- `http://example.com/admin` — the admin panel

The `install.php` file can remain in place — it automatically redirects to the homepage once a user exists.

## PHP built-in server (development)

For local development without Apache or Nginx:

```bash
php -S 127.0.0.1:8000 index.php
```

Then open `http://127.0.0.1:8000/install.php`.

## Directory structure

| Directory | Writable | Purpose |
|---|---|---|
| `content/` | Yes | Pages, posts, users, settings (flat-file database) |
| `storage/` | Yes | Logs, sessions |
| `uploads/` | Yes | User-uploaded files |
| `core/` | No | CMS engine |
| `modules/` | No* | Modules (writable if using OTA updates) |
| `themes/` | No | Theme templates |

## Troubleshooting

### Blank page or 500 error

Check the PHP error log. Common causes:

- PHP version below 8.1.0
- Missing required extension (`json`, `session`, or `openssl`)
- `content/` or `storage/` not writable by the web server

### URLs return 404

- **Apache:** ensure `mod_rewrite` is enabled and `AllowOverride All` is set
- **Nginx:** ensure the `try_files` directive routes to `index.php`
- **Built-in server:** make sure `index.php` is passed as the router argument

### Upload fails

- Check that `uploads/` is writable
- PHP defaults to a 2 MB upload limit — increase in `php.ini`:

```ini
upload_max_filesize = 64M
post_max_size = 64M
```
