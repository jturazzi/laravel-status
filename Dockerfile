# ============================================
# Production image (no frontend assets: self-contained Blade view)
# ============================================
FROM serversideup/php:8.5-frankenphp-alpine

# PHP configuration
ENV PHP_OPCACHE_ENABLE=1
ENV AUTORUN_ENABLED=true

USER root

RUN apk add --no-cache bash curl

# Prepare application directories
RUN mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache /etc/caddy  \
    && chown -R www-data:www-data /var/www/html

# Web server configuration
COPY Caddyfile /etc/caddy/Caddyfile

# Copy source code
COPY --chown=www-data:www-data . /var/www/html

# Mitigation
COPY php/99-mitigations.ini /usr/local/etc/php/conf.d/99-mitigations.ini

USER www-data
WORKDIR /var/www/html

# Install PHP dependencies (production)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress
