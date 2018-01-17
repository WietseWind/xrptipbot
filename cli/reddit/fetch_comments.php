<?php

require_once '_bootstrap.php';
require_once '/data/db.php';

echo "\nProcessing REDDIT comments (not mentions)...\n";

$query = $db->prepare('SELECT * FROM reddit_comments');
$query->execute();
$reddits = $query->fetchAll(PDO::FETCH_ASSOC);

$parent_ids = [];

if(!empty($reddits)){
    foreach($reddits as $reddit){
        echo " - /r/" . $reddit['subreddit'] . " before [".$reddit['last_id']."] \n";
        $messages = $reddit_call('/r/'.$reddit['subreddit'].'/comments?show=all&limit=250&before=' . $reddit['last_id'], 'GET');
        $first = true;
        $newMsgId = $reddit['last_id'];
        $msgCount = 0;
        $insertCount = 0;
        $matchCount = 0;
        foreach($messages->data->children as $comment){
            $comment = (array) $comment->data;
            $msgCount++;

            if ($first) {
                $newMsgId = $comment['name'];
            }

            // DEV
            // $comment['body'] .= "\n+.1 xrp";

            echo "    - [ " . $comment['name'] . " ] " . $comment['author'] . " @ " . $comment['link_title'];
            if(!preg_match("@u\/xrptipbot@i", $comment['body'])){
                if(preg_match("@(\+[ ]*[0-9\.,]+[ ]*)XRP@i", $comment['body'], $m)){
                    echo "\n";
                    echo "        >>> " . $comment['body'];
                    $data = $comment;
                    $data['body'] .= preg_replace("@[ ]+@", " ", ' -- Short syntax tip: '.$m[1].' /u/xrptipbot');
                    print_r($m);
                    // print_r($comment);

                    try {
                        $query = $db->prepare('
                            INSERT IGNORE INTO `message`
                                (`network`, `ext_id`, `type`, `from_user`, `to_user`, `subject`, `message`, `parent_id`, `context`)
                            VALUES
                                ("reddit", :ext_id, :type, :from_user, :to_user, :subject, :message, :parent_id, :context)
                        ');
                        $message = @$data['body'];
                        $type = 'sr_reaction';
                        $query->bindValue(':ext_id',    @$data['name']);
                        $query->bindValue(':type',      $type);
                        $query->bindValue(':from_user', @$data['author']);
                        $query->bindValue(':to_user',   'xrptipbot');
                        $query->bindValue(':subject',   'subreddit_reaction');
                        $query->bindParam(':message',   $message);
                        $query->bindValue(':parent_id', @$data['parent_id']);
                        $query->bindValue(':context',   @$data['permalink']);
                        $query->execute();

                        $insertId = (int) @$db->lastInsertId();
                        echo "\n Inserted: " . $insertId . "\n";
                        $matchCount++;
                        if(!empty($insertId)){
                            $insertCount++;
                        }

                        if(!empty($insertId) && !empty($data['parent_id'])){
                            $parent_ids[$data['name']] = $data['parent_id'];
                        }

                        // To mark read
                        $done_ids[] = $data['name'];
                    }
                    catch (\Throwable $e) {
                        echo "\n ERROR: " . $e->getMessage() . "\n";
                    }
                }
            }
            echo "\n";

            $first = false;
        }

        echo "\n------ DONE \n";
        echo "   - Seen:      $msgCount\n";
        echo "   - Matched:   $matchCount\n";
        echo "   - Inserted:  $insertCount\n";
        echo "   - Last ID:   $newMsgId\n";
        echo "   - SubReddit: /r/".$reddit['subreddit']."\n";

        try {
            $query = $db->prepare('
                UPDATE `reddit_comments`
                SET
                    `last_id` = :l,
                    `seen` = `seen` + :s,
                    `matched` = `matched` + :m
                WHERE
                    `subreddit` = :subreddit
                LIMIT 1
            ');
            $query->bindValue(':l', $newMsgId);
            $query->bindValue(':s', $msgCount);
            $query->bindValue(':m', $insertCount);
            $query->bindValue(':subreddit', $reddit['subreddit']);
            $query->execute();
            echo "\n>> Updated Subreddit at [ reddit_comments ]\n";
        }
        catch (\Throwable $e) {
            echo "\n ERROR: " . $e->getMessage() . "\n";
        }

        if(!empty($parent_ids)){
            echo "\nGot parent IDs (getting info...): \n";
            print_r($parent_ids);

            $parent_post = @$reddit_call('/api/info?id=' . implode(',', array_values($parent_ids)), 'GET');
            if(!empty($parent_post->data->children)){ // [0]->data->author
                $parents = $parent_post->data->children;
                foreach($parents as $p){
                    if(!empty($p->data->name)){
                        $index = array_search($p->data->name, $parent_ids);
                        $parent_author = $p->data->author;
                        echo "\n  [parent]  -> " . $p->data->name . " by " . $parent_author . ' # ' . $index. " ";
                        if(preg_match("@_@", $index)){
                            try {
                                $query = $db->prepare('UPDATE `message` SET `parent_author` = :parent_author WHERE `ext_id` = :ext_id AND `network` = "reddit" AND `parent_author` IS NULL LIMIT 1');
                                $query->bindValue(':ext_id',        $index);
                                $query->bindValue(':parent_author', $parent_author);
                                $query->execute();
                            }
                            catch (\Throwable $e) {
                                echo "\n ERROR: " . $e->getMessage() . "\n";
                            }
                        }
                    }
                }
                echo "\n";
            }
        }
    }
}


echo "\nDone\n";

