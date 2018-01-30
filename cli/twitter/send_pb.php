<?php

require_once '_bootstrap.php';

$to = preg_replace("@[^a-zA-Z0-9_\.-]@", "", (string) @$argv[1]);
$amount = preg_replace("@\.$@", "", preg_replace("@[0]+$@", "", number_format(preg_replace("@[^0-9,\.]@", "", (float) @$argv[2]), 8, '.', '')));

print_r($twitter_call('statuses/update', 'POST', [
    'status' => "@$to Your #tipbot deposit of $amount #XRP just came through :D Great! Happy tipping. More info: https://www.xrptipbot.com/howto #xrpthestandard",
]));

