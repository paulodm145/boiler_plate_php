FROM php:8.2-apache
# Alvo de producao: PHP 8.2 + Apache + MySQL. O dev usa a mesma versao de
# proposito, pra pegar incompatibilidade aqui e nao so depois do deploy.
# Ao trocar a versao, ajuste tambem app/Core/Requirements.php (MINIMUM_PHP).

# UID/GID do usuário do host, para que arquivos criados pelo container
# (uploads, .env gerado pelo instalador, etc.) fiquem com o dono certo
# no bind mount e não virem root.
ARG PUID=1000
ARG PGID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libfreetype6-dev \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql mysqli mbstring dom zip opcache gd \
    && a2enmod rewrite headers \
    && groupmod -g "${PGID}" www-data \
    && usermod -u "${PUID}" -g "${PGID}" www-data

# Caminhos relativos ao contexto de build (raiz do projeto).
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
