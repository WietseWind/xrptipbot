#!/bin/bash

sleep 10; cd /data/cli/discord/; while true; do node app.js > /data/cli/discord/app.log; echo $(date)" Crashed. Restarting..."; done
# nodemon app.js > /data/nodejs/storetransactions/app.log &
