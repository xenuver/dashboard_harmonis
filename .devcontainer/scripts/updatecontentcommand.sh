#!/bin/sh

set -e

sudo apt-get update

# configure php and composer
sudo apt-get install -y --no-install-recommends --no-install-suggests php php-cli \
     php-zip php-xml php-mysql php-gd \
     curl unzip composer
