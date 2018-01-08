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
          `to`.`balance` as _to_user_balance
        FROM  `message`
        LEFT JOIN `user` as `from` ON (`from`.`username` = `message`.`from_user`)
        LEFT JOIN `user` as `to` ON (`to`.`username` = `message`.`parent_author`)
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

            if($m['type'] == 'mention'){
                if(empty($m['parent_author'])){
                    // $msg = "Sorry, cannot determine the user you replied to when mentioning me :(";
                    $msg = '';
                }else{
                    if($m['parent_author'] == $m['from_user']){
                        $msg = "Do you want to tip yourself?! ;)";
                    }else {
                        $_toParse = html_entity_decode(trim(preg_replace("@[t\r\n ]+@", " ", $m['message'])));
                        preg_match_all("@\+[ <&lgt;\t\r\n]*([0-9,\.]+)[&lgt;> \t\r\n]*[XRPxrp]*@ms", $_toParse, $match);

                        if(!empty($match[1][0])) {
                            $amount = round( (float) str_replace(",", ".", $match[1][0]), 8);

                            if ((float) $amount > $__MAX_TIP_AMOUNT) {
                                $amount = $__MAX_TIP_AMOUNT;
                            }

                            if(substr_count($amount, '.') > 1) {
                                $msg = "Sorry, I don't know where the decimal sign and the thousands separators are. Please use only a dot as a decimal sign, and do not use a thousands separator.";
                            }else {
                                if(empty($m['_from_user_name'])){
                                    $msg = "You cannot send tips untill you **[deposit some XRP](https://www.xrptipbot.com/deposit)**...";
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
                                                                (`amount`, `from_user`, `to_user`, `reddit_post`, `sender_balance`, `recipient_balance`)
                                                                    VALUES
                                                                (:amount, :from, :to, :id, :senderbalance, :recipientbalance)
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
                                            $query = $db->prepare('UPDATE `user` SET `balance` = `balance` - :amount WHERE username = :from LIMIT 1');
                                            $query->bindValue(':amount', $amount);
                                            $query->bindValue(':from', $m['from_user']);
                                            $query->execute();

                                            $query = $db->prepare('UPDATE `user` SET `balance` = `balance` + :amount WHERE username = :to LIMIT 1');
                                            $query->bindValue(':amount', $amount);
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
                $msg = "Sorry, I only understand comments (when I am mentioned). For more information check the **[Howto](https://www.xrptipbot.com/howto)** or contact the developer of the XRP Tip Bot, /u/pepperew";
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
        }
    }
}
catch (\Throwable $e) {
    echo "\n ERROR: " . $e->getMessage();
}

echo "\n\n";