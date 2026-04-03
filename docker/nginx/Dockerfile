FROM nginx:alpine

COPY docker/nginx/nginx.conf /etc/nginx/conf.d/default.conf

WORKDIR /var/www/html

COPY . .
