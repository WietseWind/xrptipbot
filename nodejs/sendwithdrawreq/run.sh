#!/bin/bash

sleep 10; cd /data/nodejs/sendwithdrawreq/; while true; do node app.js > /data/nodejs/sendwithdrawreq/app.log; echo $(date)" Crashed. Restarting..."; done
# nodemon app.js > /data/nodejs/sendwithdrawreq/app.log &
