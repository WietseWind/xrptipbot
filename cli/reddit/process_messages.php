<?php

require_once '_bootstrap.php';
require_once '/data/db.php';

echo "\nProcessing REDDIT messages...\n";

try {
    $query = $db->prepare('
        SELECT
          `message`.*,
          `from`.`username` as _from_user_name,
          `from`.`balance` as _from_user_balance,
          `to`.`username` as _to_user_name,
          `to`.`balance` as _to_user_balance,
          `from`.`destination_wallet` as _from_user_wallet,
          `from`.`destination_tag` as _from_user_tag,
          `to`.`rejecttips` as _to_rejecttips
        FROM  `message`
        LEFT JOIN `user` as `from` ON (`from`.`username` = `message`.`from_user` AND `from`.`network` = `message`.`network`)
        LEFT JOIN `user` as `to` ON (`to`.`username` = `message`.`parent_author` AND `to`.`network` = `message`.`network`)
        WHERE
            `processed` < 1 AND
            `message`.`network` = "reddit" AND
            `message`.`from_user` != "AutoModerator" AND
            `message`.`from_user` != "xrptipbot"
        ORDER BY id ASC LIMIT 10
    ');

    // AND
    // `message`.`parent_author` != `message`.`to_user`

    $query->execute();
    $msgs = $query->fetchAll(PDO::FETCH_ASSOC);
    if(!empty($msgs)){
        foreach($msgs as $m){
            $is_valid_tip = false;
            $msg = '';
            echo "\n -> " . $m['id'] . ' @ ' . $m['network'] . '...' . "\n";

            if($m['type'] == 'mention' || $m['type'] == 'sr_reaction'){
                if(empty($m['parent_author'])){
                    // $msg = "Sorry, cannot determine the user you replied to when mentioning me :(";
                    $msg = '';
                }else{
                    if($m['parent_author'] == $m['from_user']){
                        // $msg = "Do you want to tip yourself?! ;)";
                        $msg = '';
                    }else {
                        $_toParse = html_entity_decode(trim(preg_replace("@[t\r\n ]+@", " ", $m['message'])));
                        preg_match_all("@\+[ <&lgt;\t\r\n]*([0-9,\.]+)[&lgt;> \t\r\n\/u]*[\/uXRPxrp]*@ms", $_toParse, $match);

                        if(!empty($match[1][0])) {
                            $amount = round( (float) str_replace(",", ".", $match[1][0]), 6);

                            if ((float) $amount > $__MAX_TIP_AMOUNT) {
                                $amount = $__MAX_TIP_AMOUNT;
                            }

                            if(substr_count($amount, '.') > 1) {
                                $msg = "Sorry, I don't know where the decimal sign and the thousands separators are. Please use only a dot as a decimal sign, and do not use a thousands separator.";
                            }else {
                                if(empty($m['_from_user_name'])){
                                    $msg = "You cannot send tips untill you **[deposit some XRP](https://www.xrptipbot.com/deposit)**...";
                                }elseif(!empty($m['_to_rejecttips'])){
                                    $msg = "Sorry /u/".$m['from_user'].", your Tip to /u/".$m['parent_author']." didn't go through: this user permanently disabled his/her XRPTipBot account.";
                                }else{

                                    if(empty($m['_to_user_name'])){
                                        // Create TO user
                                        $query = $db->prepare('INSERT IGNORE INTO user (username, create_reason, network) VALUES (:username, "TIPPED", "reddit")');
                                        $query->bindValue(':username', $m['parent_author']);
                                        $query->execute();
                                    }

                                    if($m['_from_user_balance'] < $amount){
                                        $msg = 'Awwww... Your Tip Bot balance is too low :( **Please [deposit](https://www.xrptipbot.com/deposit)** some XRP first and tip **'.$m['parent_author'].'** again.';
                                    }else{
                                        if(strtolower($m['parent_author']) == 'xrptipbot'){
                                            $msg = 'Thank you so much! Your donation to me, the one and only XRP Tip Bot, is very much appreciated!';
                                        }else{
                                            $usdamount = '';
                                            $bid = (float) @json_decode(@file_get_contents('https://www.bitstamp.net/api/v2/ticker_hour/xrpusd/', false, @stream_context_create(['http'=>['timeout'=>10]])))->bid;
                                            if(!empty($bid)){
                                                $usdamount = ' (' . number_format($bid * $amount, 2, '.', '') . ' USD)';
                                            }
                                            $msg = 'Awesome ' . $m['from_user'] . ', you have tipped **' . $amount . ' XRP**' . $usdamount . ' to **' . $m['parent_author'] . '**!';
                                            if(empty($m['_to_user_name'])){
                                                $msg .= "\n".'(This is the *very first* tip sent to /u/' . $m['parent_author'] . ' :D)';
                                            }
                                        }

                                        // Process TIP
                                        $query = $db->prepare('INSERT IGNORE INTO `tip`
                                                                (`amount`, `from_user`, `to_user`, `message`, `sender_balance`, `recipient_balance`, `network`)
                                                                    VALUES
                                                                (:amount, :from, :to, :id, :senderbalance, :recipientbalance, "reddit")
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
                                            $network = 'reddit';

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
                            $msg = "Sorry, I couldn't find the amount of XRP to tip... Plase use the format as described in the **[Howto](https://www.xrptipbot.com/howto)**";
                            // $msg = '';
                        }
                    }
                }
            }else{
                if (trim(strtolower($m['message'])) == 'balance' || trim(strtolower($m['subject'])) == 'balance') {
                    $msg = 'Your XRPTipBot balance is: ' . $m['_from_user_balance'] . ' XRP.';
                } elseif (trim(strtolower($m['message'])) == 'deposit' || trim(strtolower($m['subject'])) == 'deposit') {
                    $msg = 'Deposit XRP into your TipBot account by sending XRP to: ' . $m['_from_user_wallet'] . ' - PLEASE DO NOT FORGET TO ENTER THE DESTINATION TAG: ' . $m['_from_user_tag'] . '. You will receive a PM when your deposit is processed. This may take a minute.';
                } else {
                    $msg = "Sorry, I only understand comments (when I am mentioned). For more information check the **[Howto](https://www.xrptipbot.com/howto)** or contact the developer of the XRP Tip Bot, /u/pepperew";
                }
            }

            if($m['parent_author'] == 'xrptipbot' && $m['to_user'] == 'xrptipbot'){
                // Message to the XRP tip bot, process only if valid tip
                if(!$is_valid_tip){
                    $msg = '';
                }
            }

            echo "      > " . $msg;
            // Sending message ...
            echo "\n--- Sending reply --- ... \n";
            $to_post = $m['ext_id'];
            $msg_escaped = str_replace("'", "'\"'\"'", $msg);
            echo `cd /data/cli/reddit; php send_reaction.php $to_post '$msg_escaped'`;
            sleep(2);
            try {
                $query = $db->prepare('UPDATE `message` SET `processed` = 1, processed_moment = CURRENT_TIMESTAMP, action = "error", reaction = :reaction WHERE `ext_id` = :ext_id AND `network` = "reddit" AND processed_moment IS NULL LIMIT 1');
                $query->bindValue(':ext_id', $to_post);
                $query->bindValue(':reaction', $msg_escaped);
                $query->execute();
            }
            catch (\Throwable $e) {
                echo "\n ERROR: " . $e->getMessage() . "\n";
            }

        }
    }
}
catch (\Throwable $e) {
    echo "\n ERROR: " . $e->getMessage();
}

echo "\n\n";
