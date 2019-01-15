<?php

require_once '_bootstrap.php';
require_once '/data/db.php';

$at_id = preg_replace("@[^a-zA-Z0-9:_\.-\/]@", "", (string) @$argv[1]);
$parent_id = array_reverse(explode("/", $at_id))[0];
$text = @$argv[2];
$id = @$argv[3];
$original_text = $text;

if(!empty($original_text)){
    $tipboturl = ' www.xrptipbot.com';
    if (substr_count($text, 'xrptipbot.com') > 0) {
        $tipboturl = '';
    }

    $postdata = [
        'status' => $text." 🎉$tipboturl",
    ];
    if (!empty($parent_id)) {
        $postdata['attachment_url'] = 'https://twitter.com/' . $at_id;
        // $postdata['in_reply_to_status_id'] = $parent_id;
    }
    // $post = $twitter_call('/statuses/update', 'POST', $postdata);
    $pr = preg_match("/^@([^ ]+?) (.+)$/", $text, $match);
    print_r($match);
    if ($pr) {
        $user = @$twitter_call('users/lookup', 'GET', [ 'screen_name' => trim($match[1]) ]);
        if (!empty($user)) {
            if(is_array($user)){ 
		$user = @$user[0]->id;
            } else {
                $user = @$user->id;
            }
	    if (!empty($user)) {
              $post = $twitter_call('direct_messages/events/new', 'POST', [], [
                // 'status' => "@$to Your #tipbot deposit of $amount ".'XRP'." just came through :D Great! Happy tipping. More info: https://www.xrptipbot.com/howto",
                'event' => [
                    'type' => 'message_create',
                    'message_create' => [
                        'target' => [
                            'recipient_id' => $user
                        ],
                        'message_data' => [
                            'text' => trim($match[2]) . (!empty($postdata['attachment_url']) ? ' ' . $postdata['attachment_url'] . ' ' : '') . " 🎉 $tipboturl\n\n-- This is an automated message. Replies to this message will not be read or responded to. Questions? Contact @WietseWind.\n\nA maximum of five DMs will be sent in 24 hours. If you want to receive more Tip notifications, reply something (eg. 'OK') to reset this limit."
                        ]
                    ]
                ]
              ]);
              print_r($post);
           }
        }
    }
}

$callbackurl = '';
if(!empty($post->id)){
    $callbackurl = "https://twitter.com/xrptipbot/status/" . @$post->id;
    echo "\n\nPosted, $callbackurl" . ' ^ ' . @$post->text . "\n";
}elseif(!empty($post->event->id)){
    // $callbackurl = "https://twitter.com/xrptipbot/status/" . @$post->id;
    echo "\n\nPosted, ".$post->event->type." (".$post->event->id.")\n";
}else{
    if(empty($original_text)){
        echo "\n\nSuppressed, no text.\n";
    }else{
        echo "\n\[ERROR]\n";
        if(!empty($post)) print_r(@$post);
    }
}

$action = 'reaction';
if (empty($callbackurl)) {
    $action = 'ignore';
}

try {
    $field = 'ext_id';
    if (!empty($id)) $field = 'id';
    $query = $db->prepare('UPDATE `message` SET `processed` = 1, processed_moment = CURRENT_TIMESTAMP, action = :action, reaction = CONCAT(reaction, " -- ", :reaction) WHERE `'.$field.'` = :id AND `network` = "twitter" LIMIT 1');
    if (!empty($id)) {
        $query->bindValue(':id', @$id);
    } else {
        $query->bindValue(':id', @$parent_id);
    }
    $query->bindValue(':reaction', @$callbackurl);
    $query->bindValue(':action', @$action);
    $query->execute();
}
catch (\Throwable $e) {
    echo "\n ERROR: " . $e->getMessage() . "\n";
}
