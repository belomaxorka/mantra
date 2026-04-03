# Docker

Mantra CMS can run in Docker using the files in the `docker/` directory.

## Requirements

- [Docker](https://docs.docker.com/get-docker/) 23.0+
- [Docker Compose](https://docs.docker.com/compose/install/) v2

## Quick start

Build and start the container:

```bash
docker compose -f docker/docker-compose.yml up --build
```

Open `http://localhost:8080/install.php` in your browser to run the installer.

After installation the site is available at `http://localhost:8080/`.

To stop the container press `Ctrl+C` or run:

```bash
docker compose -f docker/docker-compose.yml down
```

## What's inside

The image is based on `php:8.4-apache` and includes everything the CMS needs out of the box:

- Apache with `mod_rewrite` enabled
- PHP extensions: `openssl`, `json`, `session`, `fileinfo`, `iconv`, `zlib`
- Upload limit set to 64 MB
- `expose_php` disabled

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

Caddy is included as a reverse proxy that automatically obtains and renews SSL certificates from Let's Encrypt.

### 1. Point your domain to the server

Create a DNS A record pointing your domain to the server's IP address.

### 2. Set the domain

Create a `.env` file in the `docker/` directory:

```
DOMAIN=example.com
```

### 3. Start with the production profile

```bash
docker compose -f docker/docker-compose.yml --profile production up -d --build
```

The site will be available at `https://example.com`.

Certificates are stored in the `caddy_data` Docker volume and are renewed automatically.

### Stopping

```bash
docker compose -f docker/docker-compose.yml --profile production down
```

## File structure

```
docker/
├── Caddyfile                  — Caddy reverse proxy config
├── Dockerfile                 — Application image
├── Dockerfile.dockerignore    — Build context exclusions
└── docker-compose.yml         — Service orchestration
```

## Customization

### Change the exposed port

Edit `docker/docker-compose.yml` and change the `ports` value for the `app` service:

```yaml
ports:
  - "3000:80"
```

### Change PHP settings

Edit `docker/Dockerfile` and modify the `mantra.ini` block:

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
docker compose -f docker/docker-compose.yml up --build
```
