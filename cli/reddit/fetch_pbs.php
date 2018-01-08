<?php

require_once '_bootstrap.php';
require_once '/data/db.php';

echo "\nProcessing REDDIT inbox messages...\n";

$inbox_messages = $reddit_call('/message/unread?api_type=json', 'GET');
if(!empty(@$inbox_messages->data->children)){
    $r = (array) $inbox_messages->data->children;

    $parent_ids = [];
    $done_ids = [];

    foreach($r as $d){
        $data = (array) $d->data;

        echo "\n - " . $data['name'] . ' - ' . @$data['subject'];

        try {
            $query = $db->prepare('
                INSERT IGNORE INTO `message`
                    (`network`, `ext_id`, `type`, `from_user`, `to_user`, `subject`, `message`, `parent_id`, `context`)
                VALUES
                    ("reddit", :ext_id, :type, :from_user, :to_user, :subject, :message, :parent_id, :context)
            ');
            $message = @$data['body'];
            if(empty($message) && !empty($data['body_html'])){
                $message = html_entity_decode($data['body_html']);
            }

            $type = 'pb';
            if(preg_match("@t1_@", $data['name'])){
                $type = 'mention';
            }
            $query->bindValue(':ext_id',    @$data['name']);
            $query->bindValue(':type',      $type);
            $query->bindValue(':from_user', @$data['author']);
            $query->bindValue(':to_user',   @$data['dest']);
            $query->bindValue(':subject',   @$data['subject']);
            $query->bindParam(':message',   $message);
            $query->bindValue(':parent_id', @$data['parent_id']);
            $query->bindValue(':context',   @$data['context']);
            $query->execute();

            $insertId = (int) @$db->lastInsertId();
            echo "\n Inserted: " . $insertId . "\n";

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

    if(!empty($parent_ids)){
        echo "\nGot parent IDs (getting info...): \n";
        print_r($parent_ids);

        // $parent_author = null;
        // $query->bindValue(':parent_author', $parent_author);

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

    if(!empty($done_ids)){
        echo "\nCleaning messages ...\n";
        $mrm = @$reddit_call('/api/read_message', 'POST', [ 'id' => implode(',',$done_ids) ]);
        print_r($mrm);
        echo "No need, only checking for unread\n";
    }
}

