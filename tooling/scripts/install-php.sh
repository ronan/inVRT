#!/bin/bash

set -e

# Install Composer
curl -sS https://getcomposer.org/installer -o composer-setup.php && \
php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
rm -f composer-setup.php;

