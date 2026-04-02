#!/bin/bash

set -e

apt-get update && apt-get install -y curl wget gpg ca-certificates;

mkdir -p /etc/apt/keyrings;
curl -sL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | 
    gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg;

echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_${NODE_VERSION}.x nodistro main" 
    >> /etc/apt/sources.list.d/nodesource.list;

apt-get update;
apt-get install -y nodejs;
apt-get install -y --no-install-recommends git openssh-client;
rm -rf /var/lib/apt/lists/*;
