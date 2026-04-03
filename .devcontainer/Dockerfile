FROM php:8.5-bookworm

ARG DEBIAN_FRONTEND=noninteractive
ARG NODE_VERSION=24

# Install prerequisites and task
RUN curl -1sLf 'https://dl.cloudsmith.io/public/task/task/setup.deb.sh' | sudo -E bash && \
    apt-get update && apt-get install -y curl wget gpg ca-certificates taskfile

# Install Node.js
RUN mkdir -p /etc/apt/keyrings && \
    curl -sL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg && \
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_VERSION}.x nodistro main" >> /etc/apt/sources.list.d/nodesource.list && \
    apt-get update && apt-get install -y nodejs

# Install Playwright
RUN npx playwright install-deps && npx playwright install

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer

# Clean up apt cache to reduce image size
RUN rm -rf /var/lib/apt/lists/* && rm -f composer-setup.php
