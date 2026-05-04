# Composer install
FROM composer:latest AS composer-build
WORKDIR /app
COPY composer.json composer.lock /app/
RUN composer install --no-dev --prefer-dist --optimize-autoloader


# FROM debian:bookworm AS build

# Shared base image
FROM mcr.microsoft.com/playwright:v1.59.1-noble AS base

WORKDIR /app
ARG DEBIAN_FRONTEND=noninteractive

# Install PHP 8.x
RUN apt-get update \
 && apt-get install -y \
      software-properties-common \
      curl \
    php-cli \
    php-fpm \
    php-curl \
    php-mbstring \
    php-xml \
    php-zip



FROM base AS dev
RUN apt-get update \
 && apt-get install -y \
    fish \
    vim \
    git

# taskfile and mise (todo: remove taskfile)
RUN curl https://mise.run | sh
RUN sh -c "$(curl --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

COPY . .

ENV PATH="/app/bin:${PATH}"

CMD ["fish"]

FROM base AS runtime

COPY --from=composer-build /app/vendor /app/vendor
COPY package.json package-lock.json /app/
RUN npm install --loglevel verbose --no-audit --no-fund --no-update-notifier

COPY . .

ENV PATH="/app/bin:${PATH}"

WORKDIR /dir
ENTRYPOINT [ "/app/bin/invrt" ]
