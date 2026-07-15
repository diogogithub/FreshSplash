FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libjpeg62-turbo-dev \
        libpng-dev \
        libsqlite3-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pdo_sqlite exif \
    && a2enmod headers expires \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
      /etc/apache2/sites-available/*.conf \
      /etc/apache2/apache2.conf \
      /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . /var/www/html
COPY docker/freshsplash.ini /usr/local/etc/php/conf.d/freshsplash.ini
COPY docker/entrypoint.sh /usr/local/bin/freshsplash-entrypoint

RUN chmod +x /usr/local/bin/freshsplash-entrypoint /var/www/html/bin/* \
    && mkdir -p /var/www/html/var /var/www/html/public/uploads

ENTRYPOINT ["freshsplash-entrypoint"]
CMD ["apache2-foreground"]
