FROM ubuntu:20.04

# Instalar dependências e PHP-FPM 8.3
RUN apt-get update && apt-get install -y \
    software-properties-common \
    curl \
    && add-apt-repository ppa:ondrej/php \
    && apt-get update && apt-get install -y \
    php8.3-fpm \
    php8.3-redis \
    php8.3-cli \
    php8.3-pgsql \
    php8.3-xml \
    php8.3-mbstring \
    php8.3-dom \
    php8.3-zip \
    php8.3-curl \
    libpq-dev \
    libxml2-dev \
    libonig-dev \
    nginx \
    procps \
    netcat \
    unzip \
    git \
    redis-tools \
    postgresql-client \
    && curl -sL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

# Criar diretório vendor e ajustar permissões dos diretórios storage, bootstrap/cache e vendor antes de instalar dependências
RUN mkdir -p /var/www/html/vendor \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/vendor \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/vendor

# Ajustar permissões do diretório de trabalho
RUN chown -R www-data:www-data /var/www/html

# Ajustar permissões do diretório de cache do Composer
RUN mkdir -p /var/www/.cache/composer \
    && chown -R www-data:www-data /var/www/.cache/composer \
    && chmod -R 775 /var/www/.cache/composer

# Copiar configuração do Nginx
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Copiar configuração do PHP-FPM
COPY php.ini /etc/php/8.3/fpm/php.ini
COPY www.conf /etc/php/8.3/fpm/pool.d/www.conf

# Copiar o script de entrada e garantir permissões de execução
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN mkdir -p /var/run/php && chown www-data:www-data /var/run/php && chmod 777 /var/run/php

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]