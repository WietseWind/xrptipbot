<?php

require_once '_bootstrap.php';
require_once '/data/db.php';

$at_id = preg_replace("@[^a-zA-Z0-9:_\.-\/]@", "", (string) @$argv[1]);
$text = @$argv[2];
$original_text = $text;

if(!empty($original_text)){
    $tipboturl = ' www.xrptipbot.com';
    if (substr_count($text, 'xrptipbot.com') > 0) {
        $tipboturl = '';
    }

    $post = $twitter_call('/statuses/update', 'POST', [
        'attachment_url' => 'https://twitter.com/' . $at_id,
        'status' => $text." 🎉$tipboturl #xrpthestandard",
    ]);
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
    }
}
$parent_id = array_reverse(explode("/", $at_id))[0];
$id = @$post->json->data->things[0]->data->name;
try {
    $query = $db->prepare('UPDATE `message` SET `processed` = 1, processed_moment = CURRENT_TIMESTAMP, action = "reaction", reaction = :reaction WHERE `ext_id` = :ext_id AND `network` = "twitter" LIMIT 1');
    $query->bindValue(':ext_id', $parent_id);
    $query->bindValue(':reaction', $callbackurl);
    $query->execute();
}
catch (\Throwable $e) {
    echo "\n ERROR: " . $e->getMessage() . "\n";
}
