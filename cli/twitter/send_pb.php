<?php

require_once '_bootstrap.php';

// $to = preg_replace("@[^a-zA-Z0-9_\.-]@", "", (string) @$argv[1]);
// $amount = preg_replace("@\.$@", "", preg_replace("@[0]+$@", "", number_format(preg_replace("@[^0-9,\.]@", "", (float) @$argv[2]), 8, '.', '')));

// print_r($twitter_call('statuses/update', 'POST', [
//     'status' => "@$to Your #tipbot deposit of $amount ".'XRP'." just came through :D Great! Happy tipping. More info: https://www.xrptipbot.com/howto",
// ]));

$to = preg_replace("@[^a-zA-Z0-9_\.-]@", "", (string) @$argv[1]);
$amount = preg_replace("@\.$@", "", preg_replace("@[0]+$@", "", number_format(preg_replace("@[^0-9,\.]@", "", number_format( (float) @$argv[2], 6 ) ), 8, '.', '')));

$user = @$twitter_call('users/lookup', 'GET', [ 'screen_name' => $to ])[0]->id;
if (!empty($user)) {
    print_r($twitter_call('direct_messages/events/new', 'POST', [], [
        // 'status' => "@$to Your #tipbot deposit of $amount ".'XRP'." just came through :D Great! Happy tipping. More info: https://www.xrptipbot.com/howto",
        'event' => [
            'type' => 'message_create',
            'message_create' => [
                'target' => [
                    'recipient_id' => $user
                ],
                'message_data' => [
                    'text' => "Your #tipbot deposit of $amount ".'XRP'." just came through :D Great! Happy tipping. More info: https://www.xrptipbot.com/howto\n\n-- This is an automated message. Replies to this message will not be read or responded to. Questions? Contact @WietseWind."
                ]
            ]
        ]
    ]));
}
