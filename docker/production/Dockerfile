# ===================================
# Stage 1: Base Dependencies Layer
# ===================================
FROM ubuntu:24.04 AS base-dependencies

LABEL maintainer="Laravel Optimizer Production"

ARG WWWGROUP=1337
ARG WWWUSER=1337
ARG NODE_VERSION=22
ARG MYSQL_CLIENT="mysql-client"

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Configure apt for better performance
RUN echo "Acquire::http::Pipeline-Depth 0;" > /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::http::No-Cache true;" >> /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::BrokenProxy    true;" >> /etc/apt/apt.conf.d/99custom

# Install system dependencies (including image optimization tools)
RUN apt-get update && apt-get upgrade -y \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin libpng-dev python3 dnsutils librsvg2-bin fswatch ffmpeg nano \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && apt-get update

# Install PHP (production extensions only - NO XDEBUG, NO PCOV, NO SWOOLE)
RUN apt-get install -y php8.4-cli php8.4-fpm \
       php8.4-pgsql php8.4-sqlite3 php8.4-gd \
       php8.4-curl php8.4-mongodb \
       php8.4-imap php8.4-mysql php8.4-mbstring \
       php8.4-xml php8.4-zip php8.4-bcmath php8.4-soap \
       php8.4-intl php8.4-readline \
       php8.4-ldap \
       php8.4-msgpack php8.4-igbinary php8.4-redis \
       php8.4-memcached php8.4-imagick

# Install Composer
RUN curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

# Install Node.js (production only - no pnpm, bun, yarn)
RUN curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && npm install -g npm

# Install database clients
RUN curl -sS https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor | tee /etc/apt/keyrings/pgdg.gpg >/dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt noble-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y $MYSQL_CLIENT postgresql-client-17

# Install comprehensive image optimization tools
RUN apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    # Image optimization binaries
    jpegoptim \
    optipng \
    pngquant \
    gifsicle \
    # WebP tools
    webp \
    # AVIF tools
    libavif-dev \
    libavif-bin \
    # Build dependencies for MozJPEG
    automake \
    autoconf \
    libtool \
    cmake \
    nasm \
    build-essential

# Install MozJPEG for superior JPEG compression
RUN cd /tmp && \
    git clone https://github.com/mozilla/mozjpeg.git && \
    cd mozjpeg && \
    mkdir build && \
    cd build && \
    # === IMPORTANT CHANGE HERE ===
    # Add -DBUILD_SHARED_LIBS=ON and -DCMAKE_POSITION_INDEPENDENT_CODE=ON
    cmake -DCMAKE_INSTALL_PREFIX=/usr/local -DBUILD_SHARED_LIBS=ON -DCMAKE_POSITION_INDEPENDENT_CODE=ON .. && \
    make && \
    make install && \
    ldconfig && \
    rm -rf /tmp/mozjpeg

# Clean up apt cache and temporary files
RUN apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Set PHP capabilities
RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.4

# Create application user (handle GID conflicts)
RUN userdel www-data 2>/dev/null || true && \
    groupdel www-data 2>/dev/null || true && \
    if getent group 1000 &>/dev/null; then \
        groupmod -g 1001 $(getent group 1000 | cut -d: -f1) 2>/dev/null || true; \
    fi && \
    groupadd -g $WWWGROUP www-data && \
    useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u $WWWUSER www-data

# ===================================
# Stage 2: PHP Dependencies Layer
# ===================================
FROM base-dependencies AS php-dependencies

WORKDIR /var/www/html

# Copy only dependency files for better caching
COPY composer.json composer.lock ./

# Install PHP dependencies (production only)
# This layer will be cached unless composer.json or composer.lock changes
RUN composer install --optimize-autoloader --no-dev --no-interaction --no-progress --prefer-dist --no-scripts

# ===================================
# Stage 3: Node Dependencies Layer
# ===================================
FROM base-dependencies AS node-dependencies

WORKDIR /var/www/html

# Copy only Node dependency files for better caching
COPY package.json package-lock.json ./

# Install Node dependencies
# This layer will be cached unless package.json or package-lock.json changes
RUN npm ci --only=production

# ===================================
# Stage 4: Asset Building Layer
# ===================================
FROM node-dependencies AS asset-builder

# Copy source files needed for asset building
COPY resources/ ./resources/
COPY public/ ./public/
COPY vite.config.js ./
COPY package.json package-lock.json ./

# Install all dependencies (including dev dependencies for building)
RUN npm ci

# Build frontend assets
RUN npm run build

# ===================================
# Stage 5: Final Production Image
# ===================================
FROM base-dependencies AS production

WORKDIR /var/www/html

ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="www-data"

# Copy vendor dependencies from php-dependencies layer
COPY --from=php-dependencies /var/www/html/vendor ./vendor

# Copy built assets from asset-builder layer
COPY --from=asset-builder /var/www/html/public ./public

# Copy application code (this is the only layer that changes frequently)
COPY app/ ./app/
COPY bootstrap/ ./bootstrap/
COPY config/ ./config/
COPY database/ ./database/
COPY routes/ ./routes/
COPY storage/ ./storage/
COPY resources/ ./resources/
COPY artisan composer.json composer.lock ./

# Copy production configuration files
COPY docker/production/php.ini /etc/php/8.4/cli/conf.d/99-production.ini
COPY docker/production/php-fpm.ini /etc/php/8.4/fpm/conf.d/99-production.ini
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/production/start-container /usr/local/bin/start-container

RUN chmod +x /usr/local/bin/start-container

# Set ownership and permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Create log directories with proper permissions
RUN mkdir -p /var/log/supervisor /var/log/php && \
    chown -R www-data:www-data /var/log/supervisor /var/log/php && \
    chmod -R 775 /var/log/supervisor /var/log/php

# Don't cache Laravel configuration at build time - environment variables aren't available yet
# Configuration will be cached at runtime in start-container script

# Switch to non-root user
USER www-data

EXPOSE 80

ENTRYPOINT ["start-container"] 