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

if [ ! -f signal.log ]; then
    touch signal.log
fi

chmod 666 config.json state.json signal.log

if [ -d signal-state ]; then
    chmod -R a+rwX signal-state
fi

exec apache2-foreground
