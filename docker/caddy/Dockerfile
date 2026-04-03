FROM caddy:2-alpine

COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile

WORKDIR /var/www/html

# Only copy files Caddy needs to serve (PHP is handled by php-fpm container)
COPY index.php install.php ./
COPY themes/ themes/
