FROM php:8.4-fpm-alpine

# PHP production settings
RUN { \
        echo "upload_max_filesize = 64M"; \
        echo "post_max_size = 64M"; \
        echo "expose_php = Off"; \
    } > /usr/local/etc/php/conf.d/mantra.ini

WORKDIR /var/www/html

COPY . .

RUN mkdir -p \
        content/pages content/posts content/users \
        content/settings content/categories \
        storage/logs storage/sessions \
        uploads \
    && chown -R www-data:www-data content storage uploads

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
