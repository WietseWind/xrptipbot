<?php

if(!empty($o_postdata) && is_object($o_postdata) && !empty($o_postdata->name)){
    try {
        $query = $db->prepare('
            INSERT IGNORE INTO `user`
                (`username`, `last_login`, `create_reason`)
            VALUES
                (:name, CURRENT_TIMESTAMP, "LOGIN")
        ');
        $query->bindParam(':name', $o_postdata->name);
        $query->execute();

        $insertId = (int) @$db->lastInsertId();

        if(empty($insertId)){
            $query = $db->prepare('UPDATE `user` SET `last_login` = CURRENT_TIMESTAMP WHERE `username` = :name LIMIT 1');
            $query->bindParam(':name', $o_postdata->name);
            $query->execute();
        }

        $query = $db->prepare('SELECT * FROM user WHERE username = :name');
        $query->bindParam(':name', $o_postdata->name);
        $query->execute();
        $row = $query->fetchAll(PDO::FETCH_ASSOC);

        $tipsSent = 0;
        $tipsReceived = 0;

        if(isset($row[0]) && !empty($row[0]['username'])) {
            $query = $db->prepare('SELECT count(1) as _count, sum(amount) as _sum FROM tip WHERE `from_user` = :name');
            $query->bindParam(':name', $row[0]['username']);
            $query->execute();
            $tipsSent = $query->fetch(PDO::FETCH_ASSOC);

            $query = $db->prepare('SELECT count(1) as _count, sum(amount) as _sum FROM tip WHERE `to_user` = :name');
            $query->bindParam(':name', $row[0]['username']);
            $query->execute();
            $tipsReceived = $query->fetch(PDO::FETCH_ASSOC);
        }

        /* - - - - - - - - - GET HISTORY - - - - - - - - */

        $query = $db->prepare('SELECT `tip`.*, `message`.`context` FROM `tip` LEFT JOIN `message` ON (`message`.`id` = `tip`.`reddit_post`) WHERE `tip`.`to_user` = :name ORDER BY `tip`.`id` DESC LIMIT 20');
        $query->bindParam(':name', $o_postdata->name);
        $query->execute();
        $history_received = $query->fetchAll(PDO::FETCH_ASSOC);

        $query = $db->prepare('SELECT `tip`.*, `message`.`context` FROM `tip` LEFT JOIN `message` ON (`message`.`id` = `tip`.`reddit_post`) WHERE `tip`.`from_user` = :name ORDER BY `tip`.`id` DESC LIMIT 20');
        $query->bindParam(':name', $o_postdata->name);
        $query->execute();
        $history_sent = $query->fetchAll(PDO::FETCH_ASSOC);

        $query = $db->prepare('SELECT * FROM deposit WHERE user = :name ORDER BY id DESC LIMIT 20');
        $query->bindParam(':name', $o_postdata->name);
        $query->execute();
        $history_deposits = $query->fetchAll(PDO::FETCH_ASSOC);

        $query = $db->prepare('SELECT * FROM withdraw WHERE user = :name ORDER BY id DESC LIMIT 20');
        $query->bindParam(':name', $o_postdata->name);
        $query->execute();
        $history_withdrawals = $query->fetchAll(PDO::FETCH_ASSOC);

        /* - - - - - - - END GET HISTORY - - - - - - - - */

        $json = [
            'newUser' => $insertId > 0 ? true : false,
            'user'    => $row[0],
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