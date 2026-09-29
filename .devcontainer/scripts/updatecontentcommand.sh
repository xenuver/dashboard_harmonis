#!/bin/sh

set -e

sudo apt-get update

# configure php and composer
sudo apt-get install -y --no-install-recommends --no-install suggests php8.4 php8.4-cli \
     php8.4-zip php8.4-xml php8.4-mysql php8.4-gd \
     curl unzip composer
