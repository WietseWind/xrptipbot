#!/bin/bash

sleep 10; cd /data/cli/discord/; while true; do node discord.js > /data/cli/discord/app.log; echo $(date)" Crashed. Restarting..."; done
