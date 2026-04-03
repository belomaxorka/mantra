# Docker

Mantra CMS can run in Docker with three web server options: **Apache** (default), **Nginx**, or **Caddy**.

| Variant | Base images | Containers | HTTPS |
|---|---|---|---|
| Apache | `php:8.4-apache` | 1 | via Caddy reverse proxy |
| Nginx | `nginx:alpine` + `php:8.4-fpm-alpine` | 2 | via Caddy reverse proxy |
| Caddy | `caddy:2-alpine` + `php:8.4-fpm-alpine` | 2 | built-in (Let's Encrypt) |

## 1. Install Docker

Install Docker Engine and the Compose plugin for your operating system:

| OS | Guide |
|---|---|
| Linux (Ubuntu/Debian) | [docs.docker.com/engine/install/ubuntu](https://docs.docker.com/engine/install/ubuntu/) |
| Linux (Fedora/RHEL) | [docs.docker.com/engine/install/fedora](https://docs.docker.com/engine/install/fedora/) |
| macOS | [docs.docker.com/desktop/install/mac-install](https://docs.docker.com/desktop/install/mac-install/) |
| Windows | [docs.docker.com/desktop/install/windows-install](https://docs.docker.com/desktop/install/windows-install/) |

Verify the installation:

```bash
docker --version          # Docker 23.0+
docker compose version    # Docker Compose v2+
```

> On Linux, add your user to the `docker` group to run commands without `sudo`:
>
> ```bash
> sudo usermod -aG docker $USER
> ```
>
> Log out and back in for the change to take effect.

## 2. Clone the repository

```bash
git clone https://github.com/belomaxorka/mantra.git
cd mantra
```

Or download and extract the [latest release](https://github.com/belomaxorka/mantra/releases/latest) archive.

## 3. Choose a web server and start

### Apache (default)

The simplest option — everything runs in a single container.

```bash
docker compose -f docker/docker-compose.yml up --build
```

### Nginx

Two containers: Nginx serves static files and proxies PHP requests to PHP-FPM.

```bash
docker compose -f docker/docker-compose.nginx.yml up --build
```

### Caddy

Two containers: Caddy serves static files and proxies PHP requests to PHP-FPM. Built-in automatic HTTPS when deployed with a domain.

```bash
docker compose -f docker/docker-compose.caddy.yml up --build
```

All three variants expose the site at **`http://localhost:8080`**.

## 4. Run the installer

Open `http://localhost:8080/install.php` in your browser.

Fill in the form:

- **Site Name** — displayed in the header and page title
- **Language** — English or Russian
- **Admin Username** — 3-32 characters (letters, numbers, hyphens, underscores)
- **Admin Password** — 6 characters minimum

After submitting, the installer creates all necessary directories, writes the configuration, and creates the admin user.

## 5. Done

- `http://localhost:8080/` — the site
- `http://localhost:8080/admin` — the admin panel

The `install.php` file can remain in place — it automatically redirects to the homepage once a user exists.

### Stopping

Press `Ctrl+C` in the terminal, or run:

```bash
docker compose -f docker/<compose-file> down
```

### Running in the background

Add the `-d` flag to run containers in detached mode:

```bash
docker compose -f docker/<compose-file> up -d --build
```

View logs:

```bash
docker compose -f docker/<compose-file> logs -f
```

## What's inside

All variants include:

- PHP extensions: `openssl`, `json`, `session`, `fileinfo`, `iconv`, `zlib`
- Upload limit set to 64 MB
- `expose_php` disabled
- All security rules (blocking hidden files, sensitive directories, and file extensions)

No Composer install or external services are required.

## Data persistence

Four directories are mounted as volumes so that data survives container restarts and rebuilds:

| Volume | Purpose |
|---|---|
| `content/` | Pages, posts, users, settings (flat-file database) |
| `modules/` | CMS modules (supports OTA updates) |
| `storage/` | Logs, sessions |
| `uploads/` | User-uploaded files |

All data is stored on the host filesystem — no separate database is needed.

## Production deployment with HTTPS

### Apache / Nginx — Caddy reverse proxy

Both Apache and Nginx variants include an optional Caddy reverse proxy that automatically obtains and renews SSL certificates from Let's Encrypt.

#### 1. Point your domain to the server

Create a DNS A record pointing your domain to the server's IP address.

#### 2. Set the domain

Create a `.env` file in the `docker/` directory:

```
DOMAIN=example.com
```

Or copy the example:

```bash
cp docker/.env.example docker/.env
# Edit docker/.env and uncomment/set DOMAIN
```

#### 3. Start with the production profile

Apache:

```bash
docker compose -f docker/docker-compose.yml --profile production up -d --build
```

Nginx:

```bash
docker compose -f docker/docker-compose.nginx.yml --profile production up -d --build
```

The site will be available at `https://example.com`.

#### Stopping

```bash
docker compose -f docker/<compose-file> --profile production down
```

### Caddy — built-in HTTPS

The Caddy variant handles HTTPS directly — no reverse proxy needed.

#### 1. Set the domain

Create a `.env` file in the `docker/` directory:

```
SITE_ADDRESS=example.com
HTTP_PORT=80
```

#### 2. Start

```bash
docker compose -f docker/docker-compose.caddy.yml up -d --build
```

The site will be available at `https://example.com`. Certificates are provisioned automatically.

#### Stopping

```bash
docker compose -f docker/docker-compose.caddy.yml down
```

### Certificate storage

Certificates are stored in the `caddy_data` Docker volume and are renewed automatically. The volume persists across container restarts.

## File structure

```
docker/
├── apache/
│   └── Dockerfile              — Apache + PHP image
├── nginx/
│   ├── Dockerfile              — Nginx image
│   └── nginx.conf              — Nginx server configuration
├── caddy/
│   ├── Dockerfile              — Caddy image
│   └── Caddyfile               — Caddy server configuration
├── php-fpm/
│   └── Dockerfile              — PHP-FPM image (shared by Nginx & Caddy)
├── docker-compose.yml          — Apache variant (default)
├── docker-compose.nginx.yml    — Nginx variant
├── docker-compose.caddy.yml    — Caddy variant
├── Caddyfile                   — Reverse proxy for production (Apache & Nginx)
└── .env.example                — Environment variable reference
```

## Customization

### Change the exposed port

Edit the `ports` value in the relevant `docker-compose*.yml` file:

```yaml
ports:
  - "3000:80"
```

### Change PHP settings

Edit the `RUN` block in `docker/apache/Dockerfile` or `docker/php-fpm/Dockerfile`:

```dockerfile
RUN { \
        echo "upload_max_filesize = 128M"; \
        echo "post_max_size = 128M"; \
        echo "memory_limit = 256M"; \
        echo "expose_php = Off"; \
    } > /usr/local/etc/php/conf.d/mantra.ini
```

Rebuild the image after any Dockerfile changes:

```bash
docker compose -f docker/<compose-file> up --build
```

## Troubleshooting

### Port 8080 already in use

Change the port in the compose file or stop the conflicting service:

```bash
# Check what's using port 8080
# Linux/macOS:
lsof -i :8080
# Windows:
netstat -ano | findstr :8080
```

### Permission errors on mounted volumes

On Linux, ensure the directories are writable by the container user (UID 82 for Alpine, UID 33 for Debian):

```bash
sudo chown -R 82:82 content/ storage/ uploads/    # Alpine (Nginx, Caddy)
sudo chown -R 33:33 content/ storage/ uploads/     # Debian (Apache)
```

### Container exits immediately

Check the logs:

```bash
docker compose -f docker/<compose-file> logs
```

Common causes:

- Port conflict (see above)
- Docker daemon not running — start it with `sudo systemctl start docker`
- Insufficient disk space
