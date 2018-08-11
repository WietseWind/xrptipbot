<?php

if(!empty($o_postdata) && is_object($o_postdata) && !empty($o_postdata->name)){
    try {
        $query = $db->prepare('SELECT * FROM user WHERE username = :name AND network = :network');
        $query->bindParam(':name', $o_postdata->name);
        $query->bindParam(':network', $o_postdata->type);
        $query->execute();
        $row = (object) $query->fetch(PDO::FETCH_ASSOC);

        if(!empty($row->username) && !empty($row->balance) && $row->balance > 0 && (float) $o_postdata->amount <= (float) $row->balance){
            $query = $db->prepare('
                INSERT IGNORE INTO `withdraw`
                    (`user`, `from_wallet`, `to_wallet`, `destination_tag`, `source_tag`, `amount`, `ip`, `donate`, `network`, `memo`)
                VALUES
                    (:user, :from_wallet, :to_wallet, :destination_tag, :source_tag, :amount, :ip, :donate, :network, :memo)
            ');

            $amount = (float) @$o_postdata->amount;

            if(!empty($o_postdata->donate) && preg_match("@#@", $o_postdata->donate)){
                $o_postdata->donate = 0;
            }

            if(!empty($o_postdata->donate)){
                $donateamount = (float) $o_postdata->donate;
                if($donateamount > 0 && $donateamount < $amount){
                    $amount -= $donateamount;
                }else{
                    $o_postdata->donate = 0;
                }
            }

            $memo = $o_postdata->memo;

            $query->bindValue('user', @$o_postdata->name);
            $query->bindValue('from_wallet', @$row->destination_wallet);
            $query->bindValue('to_wallet', @$o_postdata->wallet);
            $query->bindValue('destination_tag', @$o_postdata->tag);
            $query->bindValue('source_tag', @$o_postdata->srctag);
            $query->bindValue('amount', $amount);
            $query->bindValue('ip', @$o_postdata->ip);
            $query->bindValue('donate', @$o_postdata->donate);
            $query->bindValue('network', @$o_postdata->type);
            $query->bindValue('memo', $memo);
            $query->execute();

            $insertId = (int) @$db->lastInsertId();

            $json = [
                'storedWithdrawal' => $insertId > 0 ? $insertId : false
            ];

            if(!empty($insertId)){
                if (!empty($o_postdata->donate)) {
                    // Process donation
                    $query = $db->prepare('
                        INSERT IGNORE INTO `tip`
                            (`amount`, `from_user`, `to_user`, `sender_balance`, `recipient_balance`, `network`)
                        VALUES
                            (:amount, :from_user, :to_user, :sender_balance, :recipient_balance, :network)
                    ');
                    $query->bindValue(':amount', (float) $o_postdata->donate);
                    $query->bindValue(':from_user', $o_postdata->name);
                    $query->bindValue(':to_user', 'xrptipbot');
                    $query->bindValue(':sender_balance', - (float) $o_postdata->donate);
                    $query->bindValue(':recipient_balance', + (float) $o_postdata->donate);
                    $query->bindValue(':network', $o_postdata->type);
                    $query->execute();

                    $tip_insertId = (int) @$db->lastInsertId();
                    $json['tip_id'] = $tip_insertId;

                    if(!empty($tip_insertId)){
                        $query = $db->prepare('UPDATE `user` SET `balance` = `balance` + :tip WHERE `username` = :user AND `network` = :network LIMIT 1');
                        $query->bindValue(':tip', (float) $o_postdata->donate);
                        $query->bindValue(':user', 'xrptipbot');
                        $query->bindValue(':network', $o_postdata->type);
                        $query->execute();

                        $query = $db->prepare('UPDATE `user` SET `balance` = `balance` - :tip WHERE `username` = :user AND `network` = :network LIMIT 1');
                        $query->bindValue(':tip', (float) $o_postdata->donate);
                        $query->bindValue(':user', $o_postdata->name);
                        $query->bindValue(':network', $o_postdata->type);
                        $query->execute();
                    }
                }

                $query = $db->prepare('UPDATE `user` SET `balance` = `balance` - :withdraw_amount WHERE `username` = :user AND `network` = :network LIMIT 1');
                $query->bindValue(':withdraw_amount', $amount);
                $query->bindValue(':user', $o_postdata->name);
                $query->bindValue(':network', $o_postdata->type);
                $json['balance'] = $query->execute();
            }
        }
    }
    catch (\Throwable $e) {
        $json = [
            'storedWithdrawal' => -1,
            'error' => true
        ];
        // TODO: remove debug info {DEVDEVDEV}
        $json['processWithdrawal'] = $e->getMessage();
    }
}
