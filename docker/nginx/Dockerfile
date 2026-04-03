FROM nginx:stable-alpine

COPY docker/nginx/nginx.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www/html

# Only copy files nginx needs to serve (PHP is handled by php-fpm container)
COPY index.php install.php ./
COPY themes/ themes/
