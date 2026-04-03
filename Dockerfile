FROM php:8.5-bookworm

ARG DEBIAN_FRONTEND=noninteractive
ARG NODE_VERSION=24

WORKDIR /app

# Install Node.js
RUN apt-get update && \
    apt-get install -y curl wget gpg ca-certificates openssh-client && \
    mkdir -p /etc/apt/keyrings && \
    curl -sL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg && \
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_VERSION}.x nodistro main" >> /etc/apt/sources.list.d/nodesource.list && \
    apt-get update && \
    apt-get install -y nodejs && \
    apt-get install -y --no-install-recommends && \
    rm -rf /var/lib/apt/lists/*

RUN npx playwright install-deps && npx playwright install

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');"

COPY . .

# Install PHP dependencies and Node.js dependencies
RUN composer install --no-dev --optimize-autoloader
RUN npm install

WORKDIR /dir

ENTRYPOINT [ "/app/bin/invrt" ]
