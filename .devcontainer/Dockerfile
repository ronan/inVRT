ARG NODE_IMAGE=node:24-bookworm
ARG PHP_IMAGE=php:8.5-cli-bookworm
ARG WITH_PLAYWRIGHT=false
ARG WITH_DEVTOOLS=true

FROM $NODE_IMAGE AS nodesrc
FROM $PHP_IMAGE
ARG WITH_PLAYWRIGHT
ARG WITH_DEVTOOLS

# Node.js
COPY --from=nodesrc /usr/local/bin /usr/local/bin
COPY --from=nodesrc /usr/local/lib/node_modules /usr/local/lib/node_modules

# Playwright
RUN if [ "$WITH_PLAYWRIGHT" = "true" ]; then npx playwright install --with-deps chromium; fi

# System dependencies
ARG DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y sudo curl wget gpg ca-certificates && rm -rf /var/lib/apt/lists/*

# Task and Composer
RUN if [ "$WITH_DEVTOOLS" = "true" ]; then \
        sh -c "$(curl --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin && \
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer; \
    fi

COPY . /app
ENV PATH="/app/bin:${PATH}"

WORKDIR /dir
ENTRYPOINT [ "invrt" ]
