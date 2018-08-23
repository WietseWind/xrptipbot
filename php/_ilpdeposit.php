<?php

if(!empty($o_postdata) && is_object($o_postdata) && $_SERVER["HTTP_HOST"] == 'xrptipbot.internal:10060'){
    $fee = 0;
    $insertId = 0;
    $channel = '';
    $user = null;
    $exec = null;

    try {
        if (!empty($o_postdata->totalDrops) && $o_postdata->totalDrops > $fee && !empty($o_postdata->network) && !empty($o_postdata->username) && !empty($o_postdata->connectionTag) && !empty($o_postdata->sourceAccount) && !empty($o_postdata->destinationAccount) && !empty($o_postdata->sharedSecret)) {
            $query = $db->prepare('
                INSERT IGNORE INTO `ilp_deposits`
                    (`connectionTag`, `sharedSecret`, `sourceAccount`, `destinationAccount`, `drops`, `fee`, `network`, `user`)
                VALUES
                    (:connectionTag, :sharedSecret, :sourceAccount, :destinationAccount, :drops, '.$fee.', :network, :user)
            ');
            // Todo: fix dynamic, shared, fee
            $query->bindParam(':connectionTag', $o_postdata->connectionTag);
            $query->bindParam(':sharedSecret', $o_postdata->sharedSecret);
            $query->bindParam(':sourceAccount', $o_postdata->sourceAccount);
            $query->bindParam(':destinationAccount', $o_postdata->destinationAccount);
            $query->bindParam(':drops', $o_postdata->totalDrops);
            $query->bindParam(':network', $o_postdata->network);
            $query->bindParam(':user', $o_postdata->username);

            $query->execute();
            $insertId = (int) @$db->lastInsertId();
            
            if ($insertId > 0) {
                // Insert success, find user
                $query = $db->prepare('SELECT * FROM `user` WHERE `network` = :network AND (`username` = :username OR `userid` = :username) LIMIT 1');
                $query->bindParam(':username', $o_postdata->username);
                $query->bindParam(':network', $o_postdata->network);
                $query->execute();
                $user = (object) $query->fetch(PDO::FETCH_ASSOC);

                if (empty($user->destination_tag)) {
                    // User doesn't exist, let's create user
                    $query = $db->prepare('
                        INSERT IGNORE INTO `user`
                            (`username`, `create_reason`, `network`)
                        VALUES
                            (:username, "ILPDEPOSIT", :network)
                    ');
                    $query->bindParam(':network', $o_postdata->network);
                    $query->bindParam(':username', $o_postdata->username);
                    $query->execute();
                    $user->destination_tag = @$db->lastInsertId();    
                }

                if (!empty($user->destination_tag)) {
                    $tag = $user->destination_tag;
                    if (!empty($user->rejecttips)) {
                        $tag = 495; // WietseWind
                    }

                    $query = $db->prepare('
                        UPDATE `ilp_deposits` SET `user_destination_tag` = :tag WHERE `id` = :id LIMIT 1
                    ');
                    $query->bindParam(':tag', $tag);
                    $query->bindParam(':id', $insertId);      
                    $exec = $query->execute();

                    if ($exec) {
                        // Update user balance
                        $amount = ($o_postdata->totalDrops - $fee) / 1000000;
                        $query = $db->prepare('
                            UPDATE `user` SET `balance` = `balance` + :amount WHERE `destination_tag` = :tag LIMIT 1
                        ');
                        $query->bindParam(':tag', $tag);
                        $query->bindParam(':amount', $amount);      
                        $exec = $query->execute();

                        $channel = substr(md5($user->username.$user->destination_tag.$user->network),0,20);
                        /**
                         * SEND ABLY NOTIFICATION
                         **/
                        $postdata = [ 'name' => 'deposit', 'data' => json_encode([
                            'txInsertId' => $insertId,
                            'amount' => preg_replace("@\.$@", "", preg_replace("@[0]+$@", "", number_format($amount + ($fee / 1000000), 6, '.', ''))),
                            'user' => $user->username,
                            'transaction' => $o_postdata,
                            'drops' => $o_postdata->totalDrops,
                            'feeDrops' => $fee
                        ])];

                        $context = stream_context_create([ 'http' => [ 'method' => 'POST', 'header' => 'Content-type: application/x-www-form-urlencoded', 'content' => http_build_query($postdata) ] ]);
                        $result = @file_get_contents('https://'.$__ABLY_CREDENTIAL.'@rest.ably.io/channels/'.$channel.'/messages', false, $context);
                    }
                }
            }
        }

        $json = [
            'storedTransaction' => $insertId > 0 ? $insertId : -1,
            'channel' => $channel
            // 'error' => false, 'user' => $user, 'exec' => $exec,
            // 'postdata' => $o_postdata, 'server' => $_SERVER
        ];
    }
    catch (\Exception $e) {
        $json = [
            'storedTransaction' => -2,
            // 'error' => $debug ? $e->getMessage() : 'ERROR',
            // 'postdata' => $o_postdata, 'server' => $_SERVER
        ];
    }
    catch (\Throwable $e) {
        $json = [
            'storedTransaction' => -3,
            // 'error' => $debug ? $e->getMessage() : 'ERROR',
            // 'postdata' => $o_postdata, 'server' => $_SERVER
        ];
    }
}