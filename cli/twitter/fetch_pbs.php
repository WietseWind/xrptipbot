<?php

require_once '_bootstrap.php';
require_once '/data/db.php';

echo "\nProcessing TWITTER inbox messages...\n";

$mentions = $twitter_call('/statuses/mentions_timeline', 'GET', [ 'count' => 200, 'tweet_mode' => 'extended' ]);
// print_r($mentions);
// exit;

if(!empty($mentions)) {
    foreach($mentions as $m) {
        //print_r($m);
        if (empty($m->full_text) && !empty($m->text)) {
            $m->full_text = $m->text;
        }
        $m->full_text = trim(preg_replace("@[ \t\r\n]+@", " ", @$m->full_text));

        $isMultiTip = false;
        if (preg_match_all("/(@[a-zA-Z0-9_-]+[ ]*\+[ ]*[0-9\.,]+[ ]*@xrptipbot)/mis", @$m->full_text, $multiTipMatch)) {
            if (count($multiTipMatch[0]) > 1) {
                $isMultiTip = true;
                echo "\n\nMULTITIP\n\n";
                print_r($multiTipMatch[0]);
                echo "\n\n";
            }
        }

        $replyTruncated = false;
        $isThreadWithTipBotMentionedButNotByUser = false;
        // Check for '@SheilaN48661736 @xrptipbot @XRPTrump @WietseWind @BankXRP @RobertLe88 @haydentiff haha yolo +0.001 @xrptipbot'
        // vs        '@SheilaN48661736 @xrptipbot @XRPTrump @WietseWind @BankXRP @RobertLe88 @haydentiff haha yolo +0.001'
        // Eg: if the Tipbot is mentioned not by thread but by user (if thread)
        if (!$isMultiTip && preg_match("@^\@[a-zA-Z0-9_]+@", @$m->full_text)) {
            // Starts with mention, possible thread
            if (!preg_match("@^\@xrptipbot@i", @$m->full_text)) {
                // First mention isn't the TipBot
                preg_match("/^(@[a-zA-Z0-9_-]+ ){1,}/i", @$m->full_text, $all_prefixed_users);
                if ($all_prefixed_users) {
                    if (strlen($all_prefixed_users[0]) < strlen(@$m->full_text) && preg_match("@[ ]+@", trim($all_prefixed_users[0]))) { // Multiple users
                        echo "\n\n{isThreadWithTipBotMentionedButNotByUser}:\n".$m->full_text."\n ==>";
                        $replyTruncated = true;
                        $m->full_text = substr(@$m->full_text, strlen($all_prefixed_users[0]));
                        echo $m->full_text."\n\n";
                        if (!preg_match("@\@xrptipbot@i", $m->full_text)) {
                            $isThreadWithTipBotMentionedButNotByUser = true;
                        }
                    }
                }                
            }
        }

        if (!$isMultiTip && $replyTruncated && !$isThreadWithTipBotMentionedButNotByUser) {
            // Check if specific typ syntax && reply but specific tip syntax should be used
            if ($multiTipMatch && is_array($multiTipMatch) && count($multiTipMatch[0]) == 1) {
                // One time specific syntax, not multitip, 
                if (preg_match("/[^@]+((@[a-zA-Z0-9_-]+)[ ]*\+[ ]*[0-9\.,]+[ ]*@xrptipbot)/mis", @$m->full_text, $fakeParent)) {
                   echo "\nREPLY, BUT SPECIFIC TIP SYNTAX: \n   [ " . $multiTipMatch[0][0] . ' ]';
                   $parentUser = preg_replace("/^@/", "", $fakeParent[2]);
                   echo "\n       Fake parent user: " . $parentUser . "\n";
                   $m->in_reply_to_screen_name = $parentUser;
                }  
            }
        }

        $insert_loops = [ $m ];
        if ($isMultiTip) {
            $insert_loops = [];
            foreach($multiTipMatch[0] as $multiTipMention) {
                $fakeTweet = clone $m;
                $fakeTweet->text = $fakeTweet->full_text = $multiTipMention;
                $insert_loops[] = $fakeTweet;
            }
        }

        if (!$isThreadWithTipBotMentionedButNotByUser && preg_match("@\+[ ]*[0-9,\.]+@", @$m->full_text) && !preg_match("@[a-zA-Z]\+[ ]*[0-9,\.]+@", @$m->full_text) && !preg_match("@\+[ ]*[0-9,\.]+[A-Za-z]@", @$m->full_text)) { // Add ! (2nd and 3rd) to prevent things like "FOCUS+750W Gold" in a reply thread
            $loop_i = 0;
            $loop_l = count($insert_loops);
            foreach($insert_loops as $m) {
                $loop_i++;
                echo "\n - [LOOP: $loop_i/$loop_l] [Tweet:";
                echo $m->id;
                echo "] - ";
                echo @$m->user->screen_name;
                echo " <in reply to> ";
                echo @$m->in_reply_to_screen_name;
                echo " [Tweet: " . @$m->in_reply_to_status_id . "]\n";
                echo "        > " . $m->full_text;
                echo "\n";
                // print_r($m);

                try {
                    $query = $db->prepare('
                        INSERT IGNORE INTO `message`
                            (`network`, `ext_id`, `parent_id`, `parent_author`, `type`, `from_user`, `to_user`, `message`, `context`, `author_id`)
                        VALUES
                            ("twitter", :ext_id, :parent_id, :parent_author, :type, :from_user, :to_user, :message, :context, :authorid)
                    ');
                    $message = html_entity_decode($m->full_text);
                    $type = 'mention';
                    $context = '/' . @$m->user->screen_name . '/status/' . @$m->id;

                    $toSelf = (@$m->user->screen_name == @$m->in_reply_to_screen_name);
                    $tweetStartsWithMention = preg_match("@^\@[a-zA-Z0-9_]+@", $message);

                    $query->bindValue('ext_id',        @$m->id);
                    $query->bindValue('authorid',      @$m->user->id_str);
                    if (!empty(@$m->in_reply_to_status_id) && !$toSelf && !$tweetStartsWithMention) {
                        $query->bindValue('parent_id',     @$m->in_reply_to_status_id);
                        $query->bindValue('parent_author', @$m->in_reply_to_screen_name);
                        $query->bindValue('to_user',       @$m->in_reply_to_screen_name);
                    } else {
                        if (!empty(@$m->in_reply_to_screen_name) && !$toSelf && !$tweetStartsWithMention) {
                            $query->bindValue('parent_id',     0);
                            $query->bindValue('parent_author', @$m->in_reply_to_screen_name);
                            $query->bindValue('to_user',       @$m->in_reply_to_screen_name);
                        } else {
                            // Not a reaction so parse mentions
                            //   or toSelf (so probably meant to tip the first mentioned user)
                            //   or tweet starts with a mention (Thanks, @Mr_HvD!)
                            preg_match_all("@\@[a-zA-Z0-9_-]+@", $m->full_text, $usrMatch);
                            $pUser = '';
                            // Try to match any (first) user
                            if($usrMatch && is_array($usrMatch) && !empty(@$usrMatch[0])) {
                                foreach($usrMatch[0] as $possible_user) {
                                    if(strtolower($possible_user) !== '@xrptipbot' && strtolower($possible_user) !== '@' . @$m->user->screen_name){
                                        $pUser = substr($possible_user,1);
                                        break;
                                    }
                                }
                            }
                            $query->bindValue('parent_id',     0);
                            $query->bindValue('parent_author', $pUser);
                            $query->bindValue('to_user',       $pUser);
                        }
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
        } else {
            echo "\n [[ NO REGEXP MATCH ]] - [Tweet:";
            echo $m->id;
            echo "] - ";
            echo @$m->user->screen_name;
            echo " <in reply to> ";
            echo @$m->in_reply_to_screen_name;
            echo " [Tweet: " . @$m->in_reply_to_status_id . "]\n";
            echo "        > " . $m->full_text;
            echo "\n";
        }
    }
}

echo "\n";

// print_r($mentions);
