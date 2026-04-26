# Composer install
FROM composer:latest AS composer-build
WORKDIR /app
COPY composer.json composer.lock /app/
RUN composer install --no-dev --prefer-dist --optimize-autoloader

# FROM debian:bookworm AS build

# Shared base image
FROM mcr.microsoft.com/playwright:v1.59.1-noble AS base

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

WORKDIR /app

FROM base AS runtime
COPY --from=composer-build /app/vendor /app/vendor
COPY package.json package-lock.json /app/
RUN npm install --loglevel verbose --no-audit --no-fund --no-update-notifier

COPY . .

WORKDIR /dir
ENTRYPOINT [ "/app/bin/invrt" ]
