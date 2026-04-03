# Docker

Mantra CMS can run in Docker with three web server options: **Apache** (default), **Nginx**, or **Caddy**.

| Variant | Base images | Containers | HTTPS |
|---|---|---|---|
| Apache | `php:8.4-apache` | 1 | via reverse proxy |
| Nginx | `nginx:stable-alpine` + `php:8.4-fpm-alpine` | 2 | via reverse proxy |
| Caddy | `caddy:2-alpine` + `php:8.4-fpm-alpine` | 2 | built-in (Let's Encrypt) |

All variants include:

- PHP extensions: `openssl`, `json`, `session`, `fileinfo`, `iconv`, `zlib`
- Upload limit: 64 MB
- `expose_php` disabled
- Security rules: hidden files, sensitive directories, file extensions
- Health checks for all services

No Composer install or external services are required.

## Quick start

### 1. Install Docker

Install [Docker Engine](https://docs.docker.com/engine/install/) and the [Compose plugin](https://docs.docker.com/compose/install/) for your OS.

Verify:

```bash
docker --version          # Docker 23.0+
docker compose version    # Docker Compose v2+
```

> **Linux:** add your user to the `docker` group to avoid `sudo`:
>
> ```bash
> sudo usermod -aG docker $USER
> ```
>
> Log out and back in for the change to take effect.

### 2. Clone the repository

```bash
git clone https://github.com/belomaxorka/mantra.git
cd mantra
```

### 3. Start the containers

Choose one of the three variants and run:

**Apache** — single container, simplest option:

```bash
docker compose -f docker/docker-compose.yml up -d --build
```

**Nginx** — Nginx for static files, PHP-FPM for PHP:

```bash
docker compose -f docker/docker-compose.nginx.yml up -d --build
```

**Caddy** — Caddy for static files, PHP-FPM for PHP, built-in HTTPS:

```bash
docker compose -f docker/docker-compose.caddy.yml up -d --build
```

### 4. Run the installer

Open **http://localhost:8080/install.php** in your browser and fill in the form:

- **Site Name** — displayed in the header and page title
- **Language** — English or Russian
- **Admin Username** — 3-32 characters (letters, numbers, hyphens, underscores)
- **Admin Password** — 6 characters minimum

### 5. Open the site

- **http://localhost:8080/** — the site
- **http://localhost:8080/admin** — admin panel

The `install.php` file can remain in place — it automatically redirects to the homepage once a user exists.

### Managing containers

```bash
# View logs
docker compose -f docker/<compose-file> logs -f

# Stop
docker compose -f docker/<compose-file> down

# Restart
docker compose -f docker/<compose-file> up -d
```

## Data persistence

Four directories are mounted as volumes so that data survives container restarts and rebuilds:

| Volume | Purpose |
|---|---|
| `content/` | Pages, posts, users, settings (flat-file database) |
| `modules/` | CMS modules (supports live updates) |
| `storage/` | Logs, sessions |
| `uploads/` | User-uploaded files |

All data is stored on the host filesystem — no separate database is needed.

## Production deployment

### Option A: Built-in HTTPS with Caddy

The Caddy variant handles TLS certificates automatically via Let's Encrypt. No additional reverse proxy needed.

**1.** Point your domain's DNS A record to the server's IP address.

**2.** Create `docker/.env`:

```
SITE_ADDRESS=example.com
HTTP_PORT=80
```

Or copy the example: `cp docker/.env.example docker/.env`

**3.** Start:

```bash
docker compose -f docker/docker-compose.caddy.yml up -d --build
```

The site will be available at **https://example.com**.

### Option B: Caddy reverse proxy (Apache / Nginx variants)

Both Apache and Nginx variants include an optional Caddy reverse proxy that automatically obtains and renews SSL certificates from Let's Encrypt.

**1.** Point your domain's DNS A record to the server's IP address.

**2.** Create `docker/.env`:

```
DOMAIN=example.com
```

Or copy the example: `cp docker/.env.example docker/.env`

**3.** Start with the production profile:

```bash
# Apache
docker compose -f docker/docker-compose.yml --profile production up -d --build

# Nginx
docker compose -f docker/docker-compose.nginx.yml --profile production up -d --build
```

The site will be available at **https://example.com**.

To stop (including the Caddy proxy):

```bash
docker compose -f docker/<compose-file> --profile production down
```

### Option C: Behind an existing web server

If you already have Nginx or Apache running on the host, you can proxy requests to the Docker container without the built-in Caddy reverse proxy.

The container listens on port **8080** by default (configurable in the compose file). Point your existing virtual host to it:

#### Nginx

```nginx
server {
    listen 80;
    server_name mantra.example.com;

    client_max_body_size 64m;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

For HTTPS, add your certificate configuration or use Certbot:

```bash
sudo certbot --nginx -d mantra.example.com
```

#### Apache

Enable the required modules first:

```bash
sudo a2enmod proxy proxy_http
sudo systemctl restart apache2
```

Virtual host:

```apache
<VirtualHost *:80>
    ServerName mantra.example.com

    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:8080/
    ProxyPassReverse / http://127.0.0.1:8080/
</VirtualHost>
```

For HTTPS, use Certbot:

```bash
sudo certbot --apache -d mantra.example.com
```

#### Notes

- Do **not** use the `--profile production` flag — the built-in Caddy proxy is not needed since your web server handles TLS.
- Set `site_url` in the admin panel (**Settings > General**) to match the public URL (`https://mantra.example.com`).
- If the host web server and Docker are on the same machine, you can restrict the container to localhost only:

```yaml
ports:
  - "127.0.0.1:8080:80"
```

### Certificate storage

When using the built-in Caddy (Options A and B), certificates are stored in the `caddy_data` Docker volume and renewed automatically. The volume persists across container restarts.

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
├── Caddyfile                   — Reverse proxy for production (Options B)
├── entrypoint.sh               — Permission fixer for mounted volumes
└── .env.example                — Environment variable reference
```

## Troubleshooting

### Port 8080 already in use

Change the port in the compose file or find the conflicting service:

```bash
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

The entrypoint script attempts to fix ownership automatically on each start.

### Container exits immediately

Check the logs:

```bash
docker compose -f docker/<compose-file> logs
```

Common causes:

- Port conflict (see above)
- Docker daemon not running — start it with `sudo systemctl start docker`
- Insufficient disk space

### Health check failing

Check service health status:

```bash
docker compose -f docker/<compose-file> ps
```

If a service shows `unhealthy`, inspect its logs for errors.
