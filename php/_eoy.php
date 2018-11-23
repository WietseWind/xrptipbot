<?php

if(!empty($o_postdata) && is_object($o_postdata)){
    try {
        $query = $db->prepare('
            SELECT 
                a.*,
                t.moment, 
                t.amount,
                t.amount * 1000000 as TipsEOY,
                t.from_user,
                t.network,
                t.message,
                m.ext_id
            FROM (
                SELECT 
                    max(tip.id) as i, 
                    tip.from_user as f, 
                    IF(tip.from_network IS NULL, tip.network, tip.from_network) as n 
                FROM tip 
                WHERE tip.to_user = "xrptipboteoy"
                GROUP BY 
                    tip.from_user, IF(tip.from_network IS NULL, tip.network, tip.from_network)
            ) a 
            LEFT JOIN tip t ON (a.i = t.id)
            LEFT JOIN message m ON (t.message = m.id)
            ORDER BY amount DESC
        ');

        $query->execute();
        $data = $query->fetchAll(PDO::FETCH_ASSOC);
        $json['output'] = @$data;
    }
    catch (\Throwable $e) {
        $json = [
            'error' => true
        ];
    }
}