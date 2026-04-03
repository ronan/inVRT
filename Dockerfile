ARG NODE_IMAGE=node:24-bookworm
ARG PHP_IMAGE=php:8.5-cli-bookworm

FROM $NODE_IMAGE AS nodesrc
ARG WITH_PLAYWRIGHT=false

RUN if [ "$WITH_PLAYWRIGHT" = "true" ]; then npx -y playwright install --with-deps; fi

FROM $PHP_IMAGE
ARG WITH_DEVTOOLS=true

# Node.js
COPY --from=nodesrc /usr/local/bin /usr/local/bin
COPY --from=nodesrc /usr/local/lib/node_modules /usr/local/lib/node_modules

# System dependencies
ARG DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y sudo curl wget gpg ca-certificates && rm -rf /var/lib/apt/lists/*

# Task and Composer
RUN if [ "$WITH_DEVTOOLS" = "true" ]; then \
        sh -c "$(curl --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin && \
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
        php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
        rm -f composer-setup.php; \
    fi

COPY . /app

WORKDIR /dir
ENV PATH="/app/bin:${PATH}"
ENTRYPOINT [ "invrt" ]
