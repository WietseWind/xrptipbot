<?php

if(!empty($o_postdata) && is_object($o_postdata)){
    try {
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
                    tip.`amount`,
                    'tip' as `type`,
                    tip.`network`,
                    tip.`context`,
                    tip.`message`
                FROM `tip`
                ORDER BY `id` DESC
                LIMIT 200)
                -- WHERE tip.`from_user` != 'pepperew'

                UNION

                (SELECT
                    deposit.`id`,
                    deposit.`moment`,
                    deposit.`user` as `user`,
                    null as `to`,
                    deposit.`amount`,
                    'deposit' as `type`,
                    deposit.`network`,
                    '' as context,
                    null as message
                FROM `deposit`
                ORDER BY `id` DESC
                LIMIT 200)
                -- WHERE `user` != 'pepperew'

                UNION

                (SELECT
                    withdraw.`id`,
                    withdraw.`moment`,
                    withdraw.`user` as `user`,
                    null as `to`,
                    withdraw.`amount`,
                    'withdraw' as `type`,
                    withdraw.`network`,
                    '' as context,
                    null as message
                FROM `withdraw`
                WHERE `amount` != 0
                ORDER BY `id` DESC
                LIMIT 200)
                -- WHERE `user` != 'pepperew'

            ) G1
            LEFT JOIN
                `user` ufrom ON (ufrom.`username` = G1.`user` AND ufrom.`network` = G1.`network`)
            LEFT JOIN
                `user` uto ON (uto.`username` = G1.`to` AND uto.`network` = G1.`network`)
            LEFT JOIN
                `message` ON (message.id = G1.message)
            ORDER BY moment DESC
            LIMIT 200
        ");
        $query->execute();
        $json['feed'] = $query->fetchAll(PDO::FETCH_ASSOC);

        $query = $db->prepare("
            SELECT
                `balance`,
                `fee`,
                `balance` - `fee` AS `ledgerbalance`,
                `count`,
                `sum`
            FROM (
                SELECT
                    (SELECT sum(balance) as `balance` FROM `user`) as `balance`,
                    (SELECT sum(`fee`)/1000/1000 as `fee` FROM `withdraw`) as `fee`,
                    (SELECT count(1) as `count` FROM `tip`) as `count`,
                    (SELECT sum(`amount`) as `sum` FROM `tip`) as `sum`
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