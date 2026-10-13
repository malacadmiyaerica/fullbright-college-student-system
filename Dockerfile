# Fullbright College Student Management System
# Production image: PHP 8.2 (Apache) + pdo_mysql + GD
FROM php:8.2-apache

# --- System dependencies & PHP extensions -------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# --- Application code ---------------------------------------------------
WORKDIR /var/www/html
COPY . /var/www/html

# --- PHP + Apache configuration -----------------------------------------
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-fullbright.ini
COPY docker/apache-vhost.conf /etc/apache2/conf-available/fullbright.conf
RUN a2enconf fullbright

# --- Runtime directories (writable by Apache) ---------------------------
RUN mkdir -p uploads digital_id \
    && chown -R www-data:www-data uploads digital_id \
    && chmod -R 0775 uploads digital_id

# --- Entrypoint (DB wait + optional one-time schema import) -------------
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Container platform convention (Railway/Render/Fly inject $PORT).
ENV APP_ENV=production \
    SESSION_DRIVER=database \
    PORT=80

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
