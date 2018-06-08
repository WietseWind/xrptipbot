<?php

if(!empty($o_postdata) && is_object($o_postdata) && !empty($o_postdata->name)){
    try {
        $query = $db->prepare('
            INSERT IGNORE INTO `user`
                (`username`, `last_login`, `create_reason`, `network`, `userid`)
            VALUES
                (:name, CURRENT_TIMESTAMP, "LOGIN", :network, :userid)
        ');
        $query->bindParam(':name', $o_postdata->name);
        $query->bindParam(':network', $o_postdata->type);
        $userid = null;
        if (isset($o_postdata->userid)) {
            $userid = $o_postdata->userid;
        }
        $query->bindParam(':userid', $userid);
        $query->execute();

        $insertId = (int) @$db->lastInsertId();

        if(empty($insertId)){
            if (isset($o_postdata->userid)) {
                $query = $db->prepare('UPDATE `user` SET `last_login` = CURRENT_TIMESTAMP, `userid` = :userid WHERE `username` = :name AND `network` = :network LIMIT 1');
                $query->bindParam(':userid', $o_postdata->userid);
            } else {
                $query = $db->prepare('UPDATE `user` SET `last_login` = CURRENT_TIMESTAMP WHERE `username` = :name AND `network` = :network LIMIT 1');
            }
            $query->bindParam(':name', $o_postdata->name);
            $query->bindParam(':network', $o_postdata->type);
            $query->execute();
        }

        $query = $db->prepare('SELECT * FROM user WHERE username = :name AND network = :network');
        $query->bindParam(':name', $o_postdata->name);
        $query->bindParam(':network', $o_postdata->type);
        $query->execute();
        $row = $query->fetchAll(PDO::FETCH_ASSOC);

        $tipsSent = 0;
        $tipsReceived = 0;

        if(isset($row[0]) && !empty($row[0]['username'])) {
            $query = $db->prepare('SELECT count(1) as _count, sum(amount) as _sum FROM tip WHERE `from_user` = :name AND `network` = :network');
            $query->bindParam(':name', $row[0]['username']);
            $query->bindParam(':network', $row[0]['network']);
            $query->execute();
            $tipsSent = $query->fetch(PDO::FETCH_ASSOC);

            $query = $db->prepare('SELECT count(1) as _count, sum(amount) as _sum FROM tip WHERE `to_user` = :name AND `network` = :network');
            $query->bindParam(':name', $row[0]['username']);
            $query->bindParam(':network', $row[0]['network']);
            $query->execute();
            $tipsReceived = $query->fetch(PDO::FETCH_ASSOC);
        }

        /* - - - - - - - - - GET HISTORY - - - - - - - - */

        $query = $db->prepare('SELECT `tip`.*, `message`.`context`, `tip`.`context` as `tipcontext`, `user`.`userid` FROM `tip` 
            LEFT JOIN `user` ON 
            (
                (`user`.`username` = `tip`.`from_user` AND `user`.`network` = `tip`.`from_network`) 
                OR
                (`user`.`username` = `tip`.`from_user` AND `user`.`network` = :network) 
            )
            LEFT JOIN `message` ON (`message`.`id` = `tip`.`message`) 
            WHERE `tip`.`to_user` = :name AND (
                `tip`.`network` = :network
                OR
                `tip`.`to_network` = :network
            )
            ORDER BY `tip`.`id` DESC LIMIT 20');
        $query->bindParam(':name', $o_postdata->name);
        $query->bindParam(':network', $o_postdata->type);
        $query->execute();
        $history_received = $query->fetchAll(PDO::FETCH_ASSOC);

        $query = $db->prepare('SELECT `tip`.*, `message`.`context`, `tip`.`context` as `tipcontext`, `user`.`userid` FROM `tip` 
            LEFT JOIN `user` ON 
            (
                (`user`.`username` = `tip`.`to_user` AND `user`.`network` = `tip`.`to_network`) 
                OR
                (`user`.`username` = `tip`.`to_user` AND `user`.`network` = :network) 
            )
            LEFT JOIN `message` ON (`message`.`id` = `tip`.`message`) 
            WHERE `tip`.`from_user` = :name AND (
                `tip`.`network` = :network
                OR
                `tip`.`from_network` = :network
            )
            ORDER BY `tip`.`id` DESC LIMIT 20');
        $query->bindParam(':name', $o_postdata->name);
        $query->bindParam(':network', $o_postdata->type);
        $query->execute();
        $history_sent = $query->fetchAll(PDO::FETCH_ASSOC);

        $query = $db->prepare('SELECT * FROM deposit WHERE user = :name AND network = :network ORDER BY id DESC LIMIT 20');
        $query->bindParam(':name', $o_postdata->name);
        $query->bindParam(':network', $o_postdata->type);
        $query->execute();
        $history_deposits = $query->fetchAll(PDO::FETCH_ASSOC);

        $query = $db->prepare('SELECT * FROM withdraw WHERE user = :name AND network = :network ORDER BY id DESC LIMIT 20');
        $query->bindParam(':name', $o_postdata->name);
        $query->bindParam(':network', $o_postdata->type);
        $query->execute();
        $history_withdrawals = $query->fetchAll(PDO::FETCH_ASSOC);

        /* - - - - - - - END GET HISTORY - - - - - - - - */

        $json = [
            'newUser' => $insertId > 0 ? true : false,
            'user'    => $row[0],
            'network'    => $o_postdata->type,
            'channel' => substr(md5($row[0]['username'].$row[0]['destination_tag'].$row[0]['network']),0,20),
            'stats'   => [
                'tipsSent' => $tipsSent,
                'tipsReceived' => $tipsReceived,
                'balance' => @$row[0]['balance']
            ],
            'history'   => [
                'received' => $history_received,
                'sent' => $history_sent,
                'deposits' => $history_deposits,
                'withdrawals' => $history_withdrawals,
            ],
        ];
    }
    catch (\Throwable $e) {
        // print_r($e);
    }
}
