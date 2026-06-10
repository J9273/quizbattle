FROM php:8.1-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpq-dev \
    default-mysql-client

# Install MySQL extension for PHP (removed pdo_pgsql)
RUN docker-php-ext-install pdo pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port
EXPOSE 8080

ENV MYSQL_DATABASE_URL=""

# Start PHP server
CMD ["sh", "-c", "php -S 0.0.0.0:$PORT"]
