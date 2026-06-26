FROM php:8.3-cli

# -----------------------------------------------
# Instalar dependencias del sistema y extensiones PHP necesarias
# -----------------------------------------------
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    ca-certificates \
    gnupg \
    supervisor \
    libzip-dev \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql zip mbstring xml gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*



RUN echo "upload_max_filesize=200M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=220M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time=300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_file_uploads=50" >> /usr/local/etc/php/conf.d/uploads.ini



# -----------------------------------------------
# Instalar Node.js 22 LTS desde tar (evita problemas de repositorio)
# -----------------------------------------------
RUN curl -fsSL https://nodejs.org/dist/v22.12.0/node-v22.12.0-linux-x64.tar.xz -o /tmp/node.tar.xz \
    && mkdir -p /usr/local/node \
    && tar -xJf /tmp/node.tar.xz -C /usr/local/node --strip-components=1 \
    && ln -s /usr/local/node/bin/node /usr/local/bin/node \
    && ln -s /usr/local/node/bin/npm /usr/local/bin/npm \
    && ln -s /usr/local/node/bin/npx /usr/local/bin/npx \
    && rm /tmp/node.tar.xz

# -----------------------------------------------
# Instalar Composer globalmente
# -----------------------------------------------
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin \
    --filename=composer

# -----------------------------------------------
# Establecer directorio de trabajo
# -----------------------------------------------
WORKDIR /var/www/html

# ===============================================
# Optimización cache Composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

# ===============================================
# Copiar el resto del código
COPY . .

# Ejecutar scripts de Laravel
RUN composer dump-autoload --optimize
RUN php artisan package:discover --ansi

# Instalar dependencias Node si existe frontend
RUN if [ -f package.json ]; then npm install --no-audit --no-fund; fi
RUN if [ -f package.json ]; then npm run build; fi

# Ajustar permisos
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Exponer puerto
EXPOSE 8080

# Arranca el panel web Y el queue worker juntos (vía supervisor). El entrypoint
# migra la BD y cachea config antes de levantar todo.
CMD ["sh", "/var/www/html/docker/entrypoint.sh"]
