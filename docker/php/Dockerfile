FROM php:8.3-fpm-alpine

# Instalar dependências de sistema
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    icu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    bash \
    git \
    curl \
    unzip \
    su-exec

# Instalar e configurar extensões PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        zip \
        pcntl \
        opcache \
        gd

# Obter o Composer oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuração de usuário para evitar problemas de permissão
ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} appgroup && \
    adduser -u ${UID} -G appgroup -s /bin/bash -D appuser

WORKDIR /var/www/html

# Ajustar permissões
RUN chown -R appuser:appgroup /var/www/html

USER appuser

EXPOSE 9000
CMD ["php-fpm"]
