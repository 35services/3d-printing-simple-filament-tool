#!/bin/sh
set -e

cd /var/www/html

if [ ! -f config.json ]; then
    cp config.example.json config.json
fi

if [ ! -f state.json ]; then
    cp state.example.json state.json
fi

if [ ! -f slack.json ]; then
    cp slack.example.json slack.json
fi

if [ ! -f signal.json ]; then
    cp signal.example.json signal.json
fi

chmod 666 config.json state.json

exec apache2-foreground
