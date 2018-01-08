# xrptipbot.com

XRP Tip Bot (reddit, /u/xrptipbot) in PHP + NodeJS

## Note

This code needs refactoring, cleanup, etc. etc. - I know. However: I wrote this in two days ;) Will try to find some time.

# Environment

This is just the backend, processing the reddit-messages, storing it in a MySQL DB and interacting with the XRP ledger (public Ripple server).
The frontend of the XRP Tip Bot (https://www.xrptipbot.com) is written in Twig + HTML on the https://nodum.io platform. The frontend communcates
over a VPN to the backend (this repo) using HTTP POST JSON messages.

Live messaging to the client (webbrowser) is provided by https://ably.io.

# Security

Communcation with reddit: OAUTH API, secrets in `config.php`, communcates server to server (HTTPS).

Internal communication from the NodeJS scripts to the MySQL DB: internal (localhost) HTTP calls with JSON data.


# Installation (dev)

This code runs in a Ubuntu 16.04 (CLI) environment with NodeJS 8 and PHP 7 installed.
This repo should live in `/data/` (the paths are currently hardcoded). The PHP folder
is symlinked to the HTTP webroot, and a crontab is present to run all the scripts:

```
# Fetch Reddit Messages and insert into table message
* *     * * *   cd /data/cli/reddit; php fetch_pbs.php > log/fetch_pbs.txt
* *     * * *   sleep 30; cd /data/cli/reddit; php fetch_pbs.php >> log/fetch_pbs.txt

# Process message and reply
* *     * * *   cd /data/cli/reddit; php process_messages.php > log/process_messages.txt
* *     * * *   sleep 30; cd /data/cli/reddit; php process_messages.php >> log/process_messages.txt

# Process withdrawals
* *     * * *   cd /data/cli/process_withdraw; php processOne.php > log_processOne.txt
* *     * * *   sleep 30; cd /data/cli/process_withdraw; php processOne.php >> log_processOne.txt
```

There is one script that runs all the time in the background; `nodejs/storetransactions/run.sh` (in `/data/`). This
bash script runs `app.js` from the `storetransactions` folder. This script monitors the XRP
ledger for incoming transactions, and stores the transactions in the database for further processing.

The database (MySQL / MariaDB) (db: `tipbot`) schema is present in `install/db.sql`.

The config is in `config.php` and should look like this:

```
<?php

    // For push messaging on transaction sent/received
    $__ABLY_CREDENTIAL = 'xxxx:yyyyyyy';

    $__MAX_TIP_AMOUNT  = 5;

    // Monitor for transactions (to DB)
    $__WALLETS = [
        'rXXXXX',
        'rYYYYY',
        'rQQQQQ',
    ];

    // Allow sending funds
    $__SECRETS = [
        'rXXXXX' => 'sQQQQQQ',
        'rYYYYY' => 'sZZZZZZZ',
    ];

    // User & Pass of MySQL database, db 'tipbot', schema: install/db.sql
    $__DATABASE = [
        'user' => 'XXXXX',
        'pass' => 'YYYYYYYY',
    ];

    $__REDDIT_USER_AGENT = 'Cli:XrpTipBotScript:v0.0.1 (by /u/xrptipbot)';

    $__REDDIT_CLIENT_CONFIG = [
        'clientId'      => 'XXXX',
        'clientSecret'  => 'YYYYYY',
        'redirectUri'   => 'https://www.xrptipbot.com/script',
        'userAgent'     => $__REDDIT_USER_AGENT,
        'scopes'        => [ 'identity', 'edit', 'history', 'mysubreddits', 'privatemessages', 'read', 'report', 'save', 'submit' ]
    ];

    ///

    if(isset($_SERVER["PWD"]) && isset($_SERVER["TERM"]) && $_SERVER["_"]){
        // CLI called
        $configJson = json_encode([
            'wallets' => $__WALLETS,
            'secrets' => array_flip($__SECRETS),
        ]);
        file_put_contents('/data/.config.js', $configJson);
    }
```

To generate the config file for NodeJS based on the PHP config, execute `config.php` on the commandline.

To retrieve the access token for the app, no fancy installation script is present at the moment. Edit
`cli/reddit/_bootstrap.php` to (step by step) retrieve some info and exit. The last step (3) retrieves
the token from the reddit API and stores it in a json-file.

Run `composer` to install the PHP deps. in `cli/reddit`.

# Contributing

Fork, change, commit, send pull request. More to come.

