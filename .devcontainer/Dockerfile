FROM ronan4000/invrt:latest

# Docker
# RUN apt-get update && apt-get install -y docker.io && rm -rf /var/lib/apt/lists/*
# COPY --from=docker:latest /usr/local/bin/docker /usr/local/bin/docker

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Task
RUN sh -c "$(curl --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin
