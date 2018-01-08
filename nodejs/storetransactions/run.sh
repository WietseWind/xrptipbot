#!/bin/bash

sleep 10; cd /data/nodejs/storetransactions/; while true; do node app.js > /data/nodejs/storetransactions/app.log; echo $(date)" Crashed. Restarting..."; done
# nodemon app.js > /data/nodejs/storetransactions/app.log &
