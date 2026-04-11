# Composer install
FROM composer:latest AS composer
WORKDIR /app
COPY composer.json composer.lock /app/
RUN composer install --no-dev --prefer-dist --optimize-autoloader

# Final runtime image
FROM debian:bookworm AS build
# ARG WITH_PLAYWRIGHT=true
ARG DEBIAN_FRONTEND=noninteractive

FROM mcr.microsoft.com/playwright:v1.58.2-noble

# Install dependencies and add the PHP repository
# Install PHP 8.5
RUN apt-get update \
 && apt-get install -y \
      software-properties-common \
      curl \
 && add-apt-repository ppa:ondrej/php -y \
 && apt-get update \
 && apt-get install -y php8.5 php8.5-cli php8.5-common php8.5-curl \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer /app /app
WORKDIR /app
COPY . /app
RUN npm install --omit=dev
    
WORKDIR /dir
ENTRYPOINT [ "/app/bin/invrt" ]
