<?php

require_once '/data/config.php';
require_once '/data/db.php';

$from = @$_SERVER["argv"][1];
$to = @$_SERVER["argv"][2];
$amount = (float) @$_SERVER["argv"][3];
$toname = @$_SERVER["argv"][4];
$guild = @$_SERVER["argv"][5];

try {
    $query = $db->prepare('
        SELECT *, "from" as type FROM `user` WHERE `network` = "discord" and username = "'.$from.'" UNION SELECT *, "to" as type FROM `user` WHERE `network` = "discord" and username = "'.$to.'"
    ');
    $query->execute();
    $usrs = $query->fetchAll(PDO::FETCH_ASSOC);
    if(!empty($usrs)) {
        $ufrom = [];
        $uto = [];
        foreach ($usrs as $u) {
            if($u['type'] == 'from') {
                $ufrom = $u;
            }else{
                $uto = $u;
            }
        }
    }

    if (!empty($ufrom) && $amount > (float) $ufrom['balance']) {
        $amount = (float) $ufrom['balance'];
    }

    if (empty($ufrom)) {
        echo "Register your Tip Bot account first, visit https://www.xrptipbot.com/?login=discord and deposit some XRP.";
    } elseif( (float) $ufrom['balance'] == 0) {
        echo "You don't have any XRP in your Tip Bot account, deposit at https://www.xrptipbot.com/deposit";
    } else{
        if(empty($uto)){
            // Create TO user
            $query = $db->prepare('INSERT IGNORE INTO user (username, userid, create_reason, network) VALUES (:username, :userid, "TIPPED", "discord")');
            $query->bindValue(':username', $to);
            $query->bindValue(':userid', $toname);
            $query->execute();
            $uto['balance'] = 0;
        }

        $usdamount = '';
        $bid = (float) @json_decode(@file_get_contents('https://www.bitstamp.net/api/v2/ticker_hour/xrpusd/', false, @stream_context_create(['http'=>['timeout'=>4]])))->bid;
        if(!empty($bid)){
            $usdamount = ' (' . number_format($bid * $amount, 2, '.', '') . ' USD)';
        }
        $msg = 'Tipped **' . $amount . ' XRP**'.$usdamount.' to <@' . $to . '> :tada:';

        $query = $db->prepare('INSERT IGNORE INTO `tip`
                                (`amount`, `from_user`, `to_user`, `sender_balance`, `recipient_balance`, `network`, `context`)
                                    VALUES
                                (:amount, :from, :to, :senderbalance, :recipientbalance, "discord", :context)
        ');

        $query->bindValue(':amount', $amount);
        $query->bindValue(':from', $from);
        $query->bindValue(':to', $to);
        $query->bindValue(':senderbalance', - $amount);
        $query->bindValue(':recipientbalance', + $amount);
        $query->bindValue(':context', $guild);

        $query->execute();

        $insertId = (int) @$db->lastInsertId();
        $is_valid_tip = true;

        if(!empty($insertId)) {
            $network = 'discord';

            $query = $db->prepare('UPDATE `user` SET `balance` = `balance` - :amount WHERE username = :from AND `network` = :network LIMIT 1');
            $query->bindValue(':amount', $amount);
            $query->bindValue(':network', $network);
            $query->bindValue(':from', $from);
            $query->execute();

            $query = $db->prepare('UPDATE `user` SET `balance` = `balance` + :amount WHERE username = :to AND `network` = :network LIMIT 1');
            $query->bindValue(':amount', $amount);
            $query->bindValue(':network', $network);
            $query->bindValue(':to', $to);
            $query->execute();
        }

        echo $msg;

        // echo "\n\n";
        // echo "\n\n";
        // print_r($ufrom);
        // print_r($uto);
        // echo "\n\n";
        // exit;
    }
}
catch (\Throwable $e) {
    // Error
}