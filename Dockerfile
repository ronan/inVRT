# Composer install
FROM composer:latest AS composer
WORKDIR /app
COPY composer.json composer.lock /app/
RUN composer install --no-dev --prefer-dist --optimize-autoloader

# FROM debian:bookworm AS build

# Final runtime image
FROM mcr.microsoft.com/playwright:v1.58.2-noble

ARG DEBIAN_FRONTEND=noninteractive

# Install PHP 8.5
RUN apt-get update \
 && apt-get install -y \
      software-properties-common \
      curl \
 && add-apt-repository ppa:ondrej/php -y \
 && apt-get update \
 && apt-get install -y php8.5 php8.5-cli php8.5-common php8.5-curl php8.5-dom php8.5-mbstring php8.5-xml \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer /app /app
WORKDIR /app
COPY . /app
RUN npm install --loglevel error --omit=dev
    
WORKDIR /dir
ENTRYPOINT [ "/app/bin/invrt" ]
