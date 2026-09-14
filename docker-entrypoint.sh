#!/bin/sh
set -e

cd /var/www/html

if [ ! -f config.json ]; then
    cp config.example.json config.json
fi

if [ ! -f state.json ]; then
    cp state.example.json state.json
fi

chmod 666 state.json

exec apache2-foreground
