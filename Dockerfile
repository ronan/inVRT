FROM mcr.microsoft.com/playwright:v1.59.1-noble AS base

WORKDIR /app
ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
 && apt-get install -y \
      software-properties-common \
      curl

FROM base AS dev
RUN apt-get update \
 && apt-get install -y \
    fish \
    vim \
    git

# taskfile and mise (todo: remove taskfile)
RUN curl https://mise.run | sh
RUN sh -c "$(curl --location https://taskfile.dev/install.sh)" -- -d -b /usr/local/bin

COPY . .

ENV PATH="/app/bin:${PATH}"

CMD ["fish"]

FROM base AS runtime

COPY package.json package-lock.json /app/
RUN npm install --loglevel verbose --no-audit --no-fund --no-update-notifier

COPY . .

ENV PATH="/app/bin:${PATH}"

WORKDIR /dir
ENTRYPOINT [ "/app/bin/invrt" ]
