<?php

if(!empty($o_postdata) && is_object($o_postdata)){
    try {
        $query = $db->prepare("
            SELECT
                *,
                CONCAT(SUBSTRING(UPPER(type),1,1),'#',id) as id
            FROM
            (

                SELECT
                    tip.`id`,
                    tip.`moment`,
                    tip.`from_user` as `user`,
                    tip.`to_user` as `to`,
                    tip.`amount`,
                    'tip' as `type`,
                    tip.`network`
                FROM `tip`
                -- WHERE tip.`from_user` != 'pepperew'

                UNION

                SELECT
                    deposit.`id`,
                    deposit.`moment`,
                    deposit.`user` as `user`,
                    null as `to`,
                    deposit.`amount`,
                    'deposit' as `type`,
                    deposit.`network`
                FROM `deposit`
                -- WHERE `user` != 'pepperew'

                UNION

                SELECT
                    withdraw.`id`,
                    withdraw.`moment`,
                    withdraw.`user` as `user`,
                    null as `to`,
                    withdraw.`amount`,
                    'withdraw' as `type`,
                    withdraw.`network`
                FROM `withdraw`
                WHERE `amount` != 0
                -- WHERE `user` != 'pepperew'

            ) G1
            ORDER BY moment DESC
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