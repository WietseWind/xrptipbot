<?php

if(!empty($o_postdata) && is_object($o_postdata) && !empty($o_postdata->name)){
    $limit = ' LIMIT 20';

    if (!empty($o_postdata->limit)) {
        $limit = ' LIMIT ' . (int) $o_postdata->limit;
    }
    try {
        if (empty($o_postdata->stats)) {
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
        } else {
            $insertId = null;
        }

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

        if(empty($row[0]['public_destination_tag'])){
            // Fill the random public destination tag, for anonymous public deposits
            $query = $db->prepare('
                UPDATE `user`
                LEFT JOIN (
                    SELECT n FROM (
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n UNION
                        SELECT CONCAT(LPAD(CEIL(RAND()*9),1,1), LPAD(FLOOR(RAND()*9999999),6,0)) as n
                    ) R1 
                    WHERE 
                        R1.n NOT IN (
                            SELECT public_destination_tag FROM `user` WHERE public_destination_tag IS NOT NULL
                        ) 
                    LIMIT 1
                ) Rnd ON (Rnd.n > 1000)
                SET `user`.`public_destination_tag` = Rnd.n
                WHERE
                    `user`.`username` = :username
                    AND
                    `user`.`network` = :network
                    AND
                    `user`.`public_destination_tag` IS NULL
            ');
            $query->bindParam(':username', $row[0]['username']);
            $query->bindParam(':network', $row[0]['network']);
            $query->execute();
            if ($query->rowCount() > 0) {
                $query = $db->prepare('SELECT * FROM user WHERE username = :name AND network = :network');
                $query->bindParam(':name', $o_postdata->name);
                $query->bindParam(':network', $o_postdata->type);
                $query->execute();
                $row = $query->fetchAll(PDO::FETCH_ASSOC);        
            }
        }

        if ($o_postdata->type == 'twitter') {
            $query = $db->prepare("
                SELECT 
                    l.`username` u,
                    l.`balance` b
                FROM 
                    `user` l 
                WHERE 
                    l.`username` != :name
                    AND l.`balance` > 0
                    AND l.`userid` = (SELECT `userid` FROM `user` WHERE `username` = :name AND l.`network` = 'twitter')
                    AND l.`network` = 'twitter'
            ");
            $query->bindParam(':name', $o_postdata->name);
            $query->execute();
            $migrations = $query->fetchAll(PDO::FETCH_ASSOC);
        }

        $tipsSent = 0;
        $tipsReceived = 0;

        if (empty($o_postdata->nohistory)) {
            if(isset($row[0]) && !empty($row[0]['username'])) {
                $query = $db->prepare('SELECT count(1) as _count, sum(amount) as _sum FROM tip WHERE `from_user` = :name AND (`network` = :network OR `from_network` = :network)');
                $query->bindParam(':name', $row[0]['username']);
                $query->bindParam(':network', $row[0]['network']);
                $query->execute();
                $tipsSent = $query->fetch(PDO::FETCH_ASSOC);

                $query = $db->prepare('SELECT count(1) as _count, sum(amount) as _sum FROM tip WHERE `to_user` = :name AND (`network` = :network OR `to_network` = :network)');
                $query->bindParam(':name', $row[0]['username']);
                $query->bindParam(':network', $row[0]['network']);
                $query->execute();
                $tipsReceived = $query->fetch(PDO::FETCH_ASSOC);
            }

            /* - - - - - - - - - GET HISTORY - - - - - - - - */
            $app_tips = '';
            
            // if (!empty($o_postdata->app)) {
            //     $app_tips = ' AND `tip`.`network` = "app" ';
            // }

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
                '.$app_tips.'
                GROUP BY `tip`.`id`
                ORDER BY `tip`.`id` DESC '.$limit);
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
                GROUP BY `tip`.`id`
                ORDER BY `tip`.`id` DESC '.$limit);
            $query->bindParam(':name', $o_postdata->name);
            $query->bindParam(':network', $o_postdata->type);
            $query->execute();
            $history_sent = $query->fetchAll(PDO::FETCH_ASSOC);

            if (empty($o_postdata->no_on_ledger)) {
                $query = $db->prepare('SELECT * FROM deposit WHERE user = :name AND network = :network ORDER BY id DESC '.$limit);
                $query->bindParam(':name', $o_postdata->name);
                $query->bindParam(':network', $o_postdata->type);
                $query->execute();
                $history_deposits = $query->fetchAll(PDO::FETCH_ASSOC);

                $query = $db->prepare('SELECT * FROM withdraw WHERE user = :name AND network = :network ORDER BY id DESC '.$limit);
                $query->bindParam(':name', $o_postdata->name);
                $query->bindParam(':network', $o_postdata->type);
                $query->execute();
                $history_withdrawals = $query->fetchAll(PDO::FETCH_ASSOC);

                $donatedDeposits = 0;
                $query = $db->prepare('SELECT sum(amount) a FROM deposit WHERE `destination_tag` = :tag');
                $query->bindParam(':tag', $row[0]['public_destination_tag']);
                $query->execute();
                $donatedDepositSum = $query->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($donatedDepositSum)) {
                    $donatedDeposits = (float) $donatedDepositSum[0]['a'];
                }
            }

            if (empty($o_postdata->no_ilp)) {
                $ilpDeposited = 0;
                $query = $db->prepare('SELECT sum(drops) a FROM ilp_deposits WHERE `user_destination_tag` = :tag');
                $query->bindParam(':tag', $row[0]['destination_tag']);
                $query->execute();
                $ilpDonatedDepositSum = $query->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($ilpDonatedDepositSum)) {
                    $ilpDeposited = (float) $ilpDonatedDepositSum[0]['a'];
                    $ilpDeposited = $ilpDeposited / 1000000;
                }

                $query = $db->prepare('SELECT id, moment, drops, fee FROM ilp_deposits WHERE user = :name AND network = :network ORDER BY id DESC '.$limit);
                $query->bindParam(':name', $o_postdata->name);
                $query->bindParam(':network', $o_postdata->type);
                $query->execute();
                $history_ilpdeposits = $query->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        /* - - - - - - - END GET HISTORY - - - - - - - - */

        $json = [
            'newUser' => $insertId > 0 ? true : false,
            'user'    => $row[0],
            'network'    => $o_postdata->type,
            'channel' => substr(md5($row[0]['username'].$row[0]['destination_tag'].$row[0]['network']),0,20),
            'stats'   => [
                'tipsSent' => @$tipsSent,
                'tipsReceived' => @$tipsReceived,
                'donatedDeposits' => @$donatedDeposits,
                'ilpDeposited' => @$ilpDeposited,
                'balance' => @$row[0]['balance']
            ],
            'history'   => [
                'received' => @$history_received,
                'sent' => @$history_sent,
                'deposits' => @$history_deposits,
                'withdrawals' => @$history_withdrawals,
                'ilpdeposits' => @$history_ilpdeposits,
            ],
            'migrations' => empty($migrations) ? [] : $migrations
        ];
    }
    catch (\Throwable $e) {
        // print_r($e);
    }
}
