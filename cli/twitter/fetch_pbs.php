<?php

require_once '_bootstrap.php';
require_once '/data/db.php';

echo "\nProcessing TWITTER inbox messages...\n";

$mentions = $twitter_call('/statuses/mentions_timeline', 'GET', [ 'count' => 200 ]);

if(!empty($mentions)) {
    foreach($mentions as $m) {
        echo "\n - [Tweet:";
        echo $m->id;
        echo "] - ";
        echo @$m->user->screen_name;
        echo " <in reply to> ";
        echo @$m->in_reply_to_screen_name;
        echo " [Tweet: " . @$m->in_reply_to_status_id . "]\n";
        echo "        > " . $m->text;
        echo "\n";

        try {
            $query = $db->prepare('
                INSERT IGNORE INTO `message`
                    (`network`, `ext_id`, `parent_id`, `parent_author`, `type`, `from_user`, `to_user`, `message`, `context`)
                VALUES
                    ("twitter", :ext_id, :parent_id, :parent_author, :type, :from_user, :to_user, :message, :context)
            ');
            $message = html_entity_decode($m->text);
            $type = 'mention';
            $context = '/' . @$m->user->screen_name . '/status/' . @$m->id;

            $query->bindValue('ext_id',        @$m->id);
            if (!empty(@$m->in_reply_to_status_id)) {
                $query->bindValue('parent_id',     @$m->in_reply_to_status_id);
                $query->bindValue('parent_author', @$m->in_reply_to_screen_name);
                $query->bindValue('to_user',       @$m->in_reply_to_screen_name);
            } else {
                $query->bindValue('parent_id',     0);
                $query->bindValue('parent_author', '');
                $query->bindValue('to_user',       '');
            }
            $query->bindValue('type',          @$type);
            $query->bindValue('from_user',     @$m->user->screen_name);
            $query->bindValue('message',       @$message);
            $query->bindValue('context',       $context);
            $query->execute();

            $insertId = (int) @$db->lastInsertId();
            if(!empty($insertId)){
                echo "\n <<< Inserted: " . $insertId . "\n";
            } else {
                echo "\n <<< Skipped (Existing).\n";
            }
        }
        catch (\Throwable $e) {
            echo "\n ERROR: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n";

// print_r($mentions);