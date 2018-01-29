<?php

require_once '_bootstrap.php';
require_once '/data/db.php';

$at_id = preg_replace("@[^a-zA-Z0-9:_\.-\/]@", "", (string) @$argv[1]);
$parent_id = array_reverse(explode("/", $at_id))[0];
$text = @$argv[2];
$original_text = $text;

if(!empty($original_text)){
    $tipboturl = ' www.xrptipbot.com';
    if (substr_count($text, 'xrptipbot.com') > 0) {
        $tipboturl = '';
    }

    $postdata = [
        'status' => $text." 🎉$tipboturl #xrpthestandard",
    ];
    if (!empty($parent_id)) {
        $postdata['attachment_url'] = 'https://twitter.com/' . $at_id;
        $postdata['in_reply_to_status_id'] = $parent_id;
    }
    $post = $twitter_call('/statuses/update', 'POST', $postdata);
}

$callbackurl = '';
if(!empty($post->id)){
    $callbackurl = "https://twitter.com/xrptipbot/status/" . @$post->id;
    echo "\n\nPosted, $callbackurl" . ' ^ ' . @$post->text . "\n";
}else{
    if(empty($original_text)){
        echo "\n\nSuppressed, no text.\n";
    }else{
        echo "\n\[ERROR]\n";
        print_r($post);
    }
}

$action = 'reaction';
if (empty($callbackurl)) {
    $action = 'ignore';
}
try {
    $query = $db->prepare('UPDATE `message` SET `processed` = 1, processed_moment = CURRENT_TIMESTAMP, action = :action, reaction = :reaction WHERE `ext_id` = :ext_id AND `network` = "twitter" LIMIT 10');
    $query->bindValue(':ext_id', @$parent_id);
    $query->bindValue(':reaction', @$callbackurl);
    $query->bindValue(':action', @$action);
    $query->execute();
}
catch (\Throwable $e) {
    echo "\n ERROR: " . $e->getMessage() . "\n";
}
