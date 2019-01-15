<?php

require_once '_bootstrap.php';
require_once '/data/db.php';

echo "\nProcessing TWITTER messages...\n";

if((int) @file_get_contents(dirname(__FILE__).'/'.'pid') > 0){
  echo "Running, exit;";
  exit;
}

file_put_contents(dirname(__FILE__).'/'.'pid', 1);

sleep(2);

for ($i=0; $i<20; $i++){
    // Process 20 of them
    echo "Processing: $i / 20 ...\n\n";
    
    try {
        $query = $db->prepare('
            SELECT
            `message`.*,
            `from`.`username` as _from_user_name,
            `from`.`balance` as _from_user_balance,
            `to`.`username` as _to_user_name,
            `to`.`balance` as _to_user_balance,
            `to`.`rejecttips` as _to_rejecttips,
            `to`.`disablenotifications` as _to_disablenotifications
            FROM  `message`
            LEFT JOIN `user` as `from` ON (`from`.`username` = `message`.`from_user` AND `from`.`network` = `message`.`network`)
            LEFT JOIN `user` as `to` ON (`to`.`username` = `message`.`parent_author` AND `to`.`network` = `message`.`network`)
            WHERE
                `processed` < 1 AND
                `message`.`network` = "twitter" AND
                `message`.`from_user` != "xrptipbot" AND
                `message`.`moment` > DATE_SUB(NOW(), INTERVAL 100 DAY)
            ORDER BY id ASC LIMIT 1
        ');

        $query->execute();
        $msgs = $query->fetchAll(PDO::FETCH_ASSOC);
        if(!empty($msgs)){
            foreach($msgs as $m){
                $is_valid_tip = false;
                $msg = '';
                echo "\n -> " . $m['id'] . ' @ ' . $m['network'] . '...' . "\n";

                if($m['type'] == 'mention'){
                    if(empty($m['parent_author'])){
                        // $msg = "Sorry, cannot determine the user you replied to when mentioning me :(";
                        $msg = '';
                    }elseif(!empty($m['_to_rejecttips'])){
                        // $msg = "Destination user rejected tips";
                        $msg = "@".$m['from_user']." Sorry, your Tip to @".$m['parent_author']." didn't go through: this user permanently disabled his/her XRPTipBot account.";
                    }else{
                        if(strtolower($m['parent_author']) == strtolower($m['from_user'])){
                            // $msg = "Sorry @".$m['from_user'].", this didn't work. You replied to a tweet posted by yourself.";
                            $msg = '';
                        }else {
                            $_toParse = html_entity_decode(trim(preg_replace("@[t\r\n ]+@", " ", $m['message'])));
                            preg_match_all("@\+[ <&lgt;\t\r\n]*([0-9,\.]+)[&lgt;> \t\r\n\@\/u]*[\@\/uXRPxrp]*@ms", $_toParse, $match);

                            if(!empty($match[1][0])) {
                                $amount = round( (float) str_replace(",", ".", preg_replace("@[^0-9\.,]@", "", @$match[1][0])), 6);

                                if ((float) $amount > $__MAX_TIP_AMOUNT) {
                                    $amount = $__MAX_TIP_AMOUNT;
                                }

                                if(substr_count($amount, '.') > 1) {
                                    $msg = '@'.$m['from_user'] . " Sorry, I don't know where the decimal sign and the thousands separators are. Please use only a dot as a decimal sign, and do not use a thousands separator.";
                                }else {
                                    if(empty($m['_from_user_name'])){
                                        $msg = '@'.$m['from_user'] . " You cannot send tips untill you deposit some ".'XRP'." at https://www.xrptipbot.com/deposit ...";
                                    }else{

                                        if(empty($m['_to_user_name'])){
                                            // Create TO user
                                            $query = $db->prepare('INSERT IGNORE INTO user (username, create_reason, network) VALUES (:username, "TIPPED", "twitter")');
                                            $query->bindValue(':username', $m['parent_author']);
                                            $query->execute();
                                        }

                                        if($m['_from_user_balance'] < $amount){
                                            $msg = '@'.$m['from_user'].' Awwww... Your Tip Bot balance is too low :( Please deposit some XRP at https://www.xrptipbot.com/deposit first and tip @'.$m['parent_author'].' again.';
                                        }else{
                                            if(strtolower($m['parent_author']) == 'xrptipbot'){
                                                $msg = '@'.$m['from_user'].' Thank you so much! Your donation to me, the one and only XRP Tip Bot, is very much appreciated!';
                                            }else{
                                                $usdamount = '';
                                                $bid = (float) @json_decode(@file_get_contents('https://www.bitstamp.net/api/v2/ticker_hour/xrpusd/', false, @stream_context_create(['http'=>['timeout'=>10]])))->bid;
                                                if(!empty($bid)){
                                                    $usdamount = ' (' . number_format($bid * $amount, 2, '.', '') . ' USD)';
                                                }
                                                $prefix = [ 
                                                    'You have received a tip',
                                                    'Awesome, you received',
                                                    'Woohoo :) Tip time'
                                                ];
                                                $msg = '@' . $m['parent_author'] . ' ' . $prefix[array_rand($prefix)] . ': ' . $amount . ' XRP' . $usdamount . ' from @' . $m['from_user'] . ' ';
                                                // Disable, move to PM
                                                // $msg = '';
                                                if(empty($m['_to_user_name'])){
                                                    $msg .= "\n".'(About XRP: https://ripple.com/xrp/, about the XRP Tip Bot: https://www.xrptipbot.com/howto)';
                                                }
                                            }

                                            // Process TIP
                                            $query = $db->prepare('INSERT IGNORE INTO `tip`
                                                                    (`amount`, `from_user`, `to_user`, `message`, `sender_balance`, `recipient_balance`, `network`)
                                                                        VALUES
                                                                    (:amount, :from, :to, :id, :senderbalance, :recipientbalance, "twitter")
                                            ');

                                            $query->bindValue(':amount', $amount);
                                            $query->bindValue(':from', $m['from_user']);
                                            $query->bindValue(':to', $m['parent_author']);
                                            $query->bindValue(':id', $m['id']);
                                            $query->bindValue(':senderbalance', - $amount);
                                            $query->bindValue(':recipientbalance', + $amount);

                                            $query->execute();

                                            $insertId = (int) @$db->lastInsertId();
                                            $is_valid_tip = true;

                                            if(!empty($insertId)) {
                                                $network = 'twitter';

                                                $query = $db->prepare('UPDATE `user` SET `balance` = `balance` - :amount WHERE username = :from AND `network` = :network LIMIT 1');
                                                $query->bindValue(':amount', $amount);
                                                $query->bindValue(':network', $network);
                                                $query->bindValue(':from', $m['from_user']);
                                                $query->execute();

                                                $query = $db->prepare('UPDATE `user` SET `balance` = `balance` + :amount WHERE username = :to AND `network` = :network LIMIT 1');
                                                $query->bindValue(':amount', $amount);
                                                $query->bindValue(':network', $network);
                                                $query->bindValue(':to', $m['parent_author']);
                                                $query->execute();
                                            }
                                        }
                                    }
                                }
                            }else {
                                // $msg  = "<< PARSE MSG, NO MATCH >>: [" . $m['message'] . "] \n";
                                // $msg .= "\n------------------------------------------\n";
                                // $msg = "Sorry, I couldn't find the amount of #XRP to tip... Plase use the format as described in the Howto at https://www.xrptipbot.com/howto";
                                $msg = '';
                            }
                        }
                    }
                }else{
                    // $msg = "Sorry, I only understand comments (when I am mentioned). For more information check the Howto at https://www.xrptipbot.com/howto or contact the developer of the #XRP Tip Bot, @WietseWind";
                    $msg = "";
                }

                if(strtolower($m['parent_author']) == 'xrptipbot' && strtolower($m['to_user']) == 'xrptipbot'){
                    // Message to the XRP tip bot, process only if valid tip
                    if(!$is_valid_tip){
                        $msg = '';
                    }
                }

                echo "      > " . $msg;
                // Sending message ...
                echo "\n--- Sending reply --- ... \n";
                $to_post = $m['from_user']. '/status/' . $m['ext_id'];
                $msg_escaped = str_replace("'", "'\"'\"'", $msg);
                $msg_escaped = trim(preg_replace("@[ \t\r\n]+@", " ", $msg_escaped));
                if ($m['_to_disablenotifications'] < 1) {
                    echo `cd /data/cli/twitter; php send_reaction.php '$to_post' '$msg_escaped'`;
                } else {
                    // echo "NOTIFICATIONS TO TO_USER DISABLED";
            try {
                $query = $db->prepare('UPDATE `message` SET `processed` = 1, processed_moment = CURRENT_TIMESTAMP WHERE `ext_id` = :ext_id LIMIT 1');
                $query->bindValue(':ext_id', $m['ext_id']);
                $query->execute();
            }
            catch (\Throwable $e) {
                echo "\n ERROR: " . $e->getMessage() . "\n";
            }
                }
                sleep(2);
            }
        }
    }
    catch (\Throwable $e) {
        echo "\n ERROR: " . $e->getMessage();
    }
}
file_put_contents(dirname(__FILE__).'/'.'pid', 0);

echo "\n\n";
