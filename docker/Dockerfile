FROM php:8.4-apache

RUN a2enmod rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && { \
        echo "upload_max_filesize = 64M"; \
        echo "post_max_size = 64M"; \
        echo "expose_php = Off"; \
    } > /usr/local/etc/php/conf.d/mantra.ini

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY . .

RUN mkdir -p \
        content/pages content/posts content/users \
        content/settings content/categories \
        storage/logs storage/sessions \
        uploads \
    && chown -R www-data:www-data content storage uploads

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
