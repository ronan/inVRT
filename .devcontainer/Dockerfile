ARG NODE_IMAGE=node:24-bookworm
ARG PHP_IMAGE=php:8-cli-bookworm
ARG WITH_DEVTOOLS=true
ARG WITH_PLAYWRIGHT=false

FROM $NODE_IMAGE AS nodesrc

RUN if [ "$WITH_PLAYWRIGHT" = "true" ]; then npx -y playwright install --with-deps; fi

FROM $PHP_IMAGE

ENV PATH="/app/bin:${PATH}"

# Node.js
COPY --from=nodesrc /usr/local/bin /usr/local/bin
COPY --from=nodesrc /usr/local/lib/node_modules /usr/local/lib/node_modules

ARG WITH_DEVTOOLS
ARG WITH_PLAYWRIGHT

ARG DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y sudo curl wget gpg ca-certificates && rm -rf /var/lib/apt/lists/*

# Task and Composer
RUN if [ "$WITH_DEVTOOLS" = "true" ]; then \
        sh -c "$(curl --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin && \
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
        php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
        rm -f composer-setup.php; \
    fi
