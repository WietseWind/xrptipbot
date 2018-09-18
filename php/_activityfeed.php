<?php

$limit = 1000;

if(!empty($o_postdata) && is_object($o_postdata)){
    $skip = 0;
    if (!empty($_GET["skip"])) {
        $skip = (int) $_GET["skip"];
    }
    if (!empty($_GET["limit"])) {
        $limit = (int) $_GET["limit"];
        if ($limit < 10) $limit = 10;
        if ($limit > 10000) $limit = 10000;
    }
    
    $subqLimit = '';
    if ($skip == 0) {
        $subqLimit = ' LIMIT ' . $limit;
    }

    try {
        if (!empty($_GET["ilp"])) {
            $query = $db->prepare("
                SELECT
                    G1.*,
                    CONCAT(SUBSTRING(UPPER(G1.type),1,1),'#',G1.id) as id,
                    ufrom.userid as user_id,
                    uto.userid as to_id,
                    IF(G1.context IS NULL, message.context, G1.context) as context
                FROM
                (
                    (SELECT
                        ilp_deposits.`id` as id,
                        ilp_deposits.`moment` as moment,
                        ilp_deposits.`user` as `user`,
                        null as `to`,
                        null as `user_network`,
                        null as `to_network`,
                        ilp_deposits.`drops` - ilp_deposits.`fee` / 1000000 as amount,
                        'ILP deposit' as `type`,
                        ilp_deposits.`network`,
                        '' as context,
                        null as message
                    FROM `ilp_deposits`
                    ORDER BY `moment` DESC, `amount` DESC $subqLimit)

                ) G1
                LEFT JOIN
                    `user` ufrom ON ( (ufrom.`username` = G1.`user` AND ufrom.`network` = G1.`network`) OR (ufrom.`username` = G1.`user` AND ufrom.`network` = G1.`user_network`) )
                LEFT JOIN
                    `user` uto ON ( (uto.`username` = G1.`to` AND uto.`network` = G1.`network`) OR (uto.`username` = G1.`to` AND uto.`network` = G1.`to_network`) )
                LEFT JOIN
                    `message` ON (message.id = G1.message)
                ORDER BY moment DESC
                LIMIT $skip, $limit
            ");
        } else {
            $query = $db->prepare("
                SELECT
                    G1.*,
                    CONCAT(SUBSTRING(UPPER(G1.type),1,1),'#',G1.id) as id,
                    ufrom.userid as user_id,
                    uto.userid as to_id,
                    IF(G1.context IS NULL, message.context, G1.context) as context
                FROM
                (
                    (SELECT
                        tip.`id`,
                        tip.`moment`,
                        tip.`from_user` as `user`,
                        tip.`to_user` as `to`,
                        tip.`from_network` as `user_network`,
                        tip.`to_network` as `to_network`,
                        tip.`amount`,
                        'tip' as `type`,
                        tip.`network`,
                        tip.`context`,
                        tip.`message`
                    FROM `tip`
                    ORDER BY `id` DESC $subqLimit)

                    UNION

                    (SELECT
                        deposit.`id`,
                        deposit.`moment`,
                        deposit.`user` as `user`,
                        null as `to`,
                        null as `user_network`,
                        null as `to_network`,
                        deposit.`amount`,
                        'deposit' as `type`,
                        deposit.`network`,
                        '' as context,
                        null as message
                    FROM `deposit`
                    ORDER BY `id` DESC $subqLimit)

                    UNION

                    (SELECT
                        withdraw.`id`,
                        withdraw.`moment`,
                        withdraw.`user` as `user`,
                        null as `to`,
                        null as `user_network`,
                        null as `to_network`,
                        withdraw.`amount`,
                        'withdraw' as `type`,
                        withdraw.`network`,
                        '' as context,
                        null as message
                    FROM `withdraw`
                    WHERE `amount` != 0
                    ORDER BY `id` DESC $subqLimit)

                ) G1
                LEFT JOIN
                    `user` ufrom ON ( (ufrom.`username` = G1.`user` AND ufrom.`network` = G1.`network`) OR (ufrom.`username` = G1.`user` AND ufrom.`network` = G1.`user_network`) )
                LEFT JOIN
                    `user` uto ON ( (uto.`username` = G1.`to` AND uto.`network` = G1.`network`) OR (uto.`username` = G1.`to` AND uto.`network` = G1.`to_network`) )
                LEFT JOIN
                    `message` ON (message.id = G1.message)
                ORDER BY moment DESC
                LIMIT $skip, $limit
            ");
        }
        $query->execute();
        $json['feed'] = $query->fetchAll(PDO::FETCH_ASSOC);

        $query = $db->prepare("
            SELECT
                `balance`,
                `fee`,
                `balance` - `fee` AS `ledgerbalance`,
                `count`,
                `sum`,
                `ilpsum`
            FROM (
                SELECT
                    (SELECT sum(balance) as `balance` FROM `user`) as `balance`,
                    (SELECT sum(`fee`)/1000/1000 as `fee` FROM `withdraw`) as `fee`,
                    (SELECT count(1) as `count` FROM `tip`) as `count`,
                    (SELECT sum(`amount`) as `sum` FROM `tip`) as `sum`,
                    (SELECT sum(`drops`) / 1000000 as `ilpsum` FROM `ilp_deposits`) as `ilpsum`
            ) G2
        ");
        $query->execute();
        $json['stats'] = $query->fetch(PDO::FETCH_ASSOC);
    }
    catch (\Throwable $e) {
        $json = [
            'error' => true
        ];
        // TODO: remove debug info {DEVDEVDEV}
        // $json['msg'] = $e->getMessage();
    }
}