<?php

require_once '_bootstrap.php';

$to = preg_replace("@[^a-zA-Z0-9_\.-]@", "", (string) @$argv[1]);
$amount = preg_replace("@\.$@", "", preg_replace("@[0]+$@", "", number_format(preg_replace("@[^0-9,\.]@", "", (float) @$argv[2]), 8, '.', '')));

print_r($reddit_call('/api/compose', 'POST', [
    'api_type' => 'json',
    'to' => $to,
    'subject' => 'Deposit of XRP confirmed :)',
    'text' => "Your deposit of **$amount XRP** just came trough :D\n\nGreat! Happy tipping.\n\nMore info: https://www.xrptipbot.com/howto",
]));

