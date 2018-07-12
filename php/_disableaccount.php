<?php

if(!empty($o_postdata) && is_object($o_postdata) && !empty($o_postdata->name)){
    try {
        $query = $db->prepare('SELECT * FROM user WHERE username = :name AND network = :network');
        $query->bindParam(':name', $o_postdata->name);
        $query->bindParam(':network', $o_postdata->type);
        $query->execute();
        $row = (object) $query->fetch(PDO::FETCH_ASSOC);

        if(!empty($row->username)){
            if (!empty($row->balance) && $row->balance > 0) {
                // {% set backend = sabre.post('@http://37.139.19.52:10060/index.php/btntip', {
                //     from: {
                //         user: session.get.__account.user.username,
                //         network: session.get.__account.user.network
                //     },
                //     to: {
                //         user: data.to,
                //         network: data.network
                //     },
                //     amount: amount,
                //     url: data.link|s|split('?').0
                // }|json_encode) %}
                $o_postdata->from = (object) [];
                $o_postdata->from->user = $o_postdata->name;
                $o_postdata->from->network = $o_postdata->type;
                $o_postdata->to = (object) [];
                $o_postdata->to->user = 'WietseWind';
                $o_postdata->to->network = 'twitter';
                $o_postdata->amount = $row->balance;
                $o_postdata->url = 'https://www.xrptipbot.com/account/disable';
                $o_postdata->noLimit = true;

                @include_once(dirname(__FILE__) . '/_btntip.php');
            }
            
            $query = $db->prepare('UPDATE `user` SET `rejecttips` = CURRENT_TIMESTAMP WHERE `username` = :user AND `network` = :network LIMIT 1');

            $query->bindValue('user', @$o_postdata->name);
            $query->bindValue('network', @$o_postdata->type);
            $query->execute();

            $json = [
                'disabledAccount' => 1,
                'error' => false,
                'accountData' => (array) $row,
                '_preJson' => @$json
            ];
        }
    }
    catch (\Throwable $e) {
        $json = [
            'disabledAccount' => -1,
            'error' => true
        ];
        // TODO: remove debug info {DEVDEVDEV}
        $json['processWithdrawal'] = $e->getMessage();
    }
}
