FROM ronan4000/invrt:latest

# Docker
# RUN apt-get update && apt-get install -y docker.io && rm -rf /var/lib/apt/lists/*
# COPY --from=docker:latest /usr/local/bin/docker /usr/local/bin/docker

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Task
RUN sh -c "$(curl --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin

# Neovim

RUN apt-get update \
 && apt-get install -y \
      build-essential\
      ripgrep \
      fd-find \
      fzf \
 && add-apt-repository ppa:neovim-ppa/unstable -y \
 && apt-get update \
 && apt-get install -y neovim \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*
