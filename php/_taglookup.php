<?php

if(!empty($o_postdata) && is_object($o_postdata)){
    try {
        $json['input'] = (string) @$o_postdata->addr;
        if (preg_match("@.*(rPEPPER7kfTD9w2To4CQk6UCfuHM9c6GDY).+dt=([0-9]+)@", $json['input'], $m)) {
            $json['match'] = $m;
            $query = $db->prepare("SELECT * FROM `user` WHERE `destination_wallet` = :wallet AND (`destination_tag` = :tag OR `public_destination_tag` = :tag) LIMIT 1");
            $query->bindParam(':wallet', $json['match'][1]);
            $query->bindParam(':tag', $json['match'][2]);    
            $query->execute();
            $users = $query->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($users)) {
                $userRef = $users[0]['username'];
                if ($users[0]['network'] == 'discord') {
                    $userRef = $users[0]['userid'];
                }
                $json['user'] = 'xrptipbot://' . $users[0]['network'] . '/' . $userRef;
            }
        }
        if (preg_match("@rPdvC6ccq8hCdPKSPJkPmyZ4Mi1oG2FFkT@", $json['input'])) {
            $json['user'] = 'xrptipbot://twitter/WietseWind';
        }
    }
    catch (\Throwable $e) {
        $json = [
            'error' => true
        ];
    }
}