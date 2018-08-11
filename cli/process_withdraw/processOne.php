<?php

require_once '/data/db.php';

try {
    $query = $db->prepare('SELECT * FROM `withdraw` WHERE `processed` IS NULL ORDER BY `id` ASC LIMIT 1');

    $query->execute();
    $req = $query->fetch(PDO::FETCH_ASSOC);
    if(!empty($req)){
        echo "\n Processing: \n";
        print_r($req);

        $query = $db->prepare('UPDATE `withdraw` SET `processed` = CURRENT_TIMESTAMP WHERE `id` = :id LIMIT 1');
        $query->bindValue(':id', $req['id']);
        $query->execute();

        $att = ((float)$req['amount']).':'.$req['from_wallet'].':'.$req['to_wallet'].':'.((int)$req['destination_tag']).':'.((int)$req['source_tag']).':'.(@strtoupper(@bin2hex(@utf8_decode($req['memo']))));
        echo "Processing...";

        $md = md5($att);
        @exec("cd /data/nodejs/sendwithdrawreq; nodejs app.js $att 2>&1 > app.log.$md ; cat app.log.$md; cat app.log.$md > app.log; rm app.log.$md;", $retArr, $retVal);
        echo "Done;\n";

        $retstr = trim(implode("\n", $retArr));
        echo $retstr."\n";

        $query = $db->prepare('UPDATE `withdraw` SET `log` = :log WHERE `id` = :id LIMIT 1');
        $query->bindValue(':id', $req['id']);
        $query->bindValue(':log', $retstr);
        $query->execute();

        if(preg_match("@Signed TX.+ID.+:(.+)@", $retstr, $m)){
            $ledger = -1;
            $txid = @trim($m[1]);
            $fee = null;

            if(preg_match("@result.+tesSUCCESS@", $retstr)){
                preg_match("@ledgerVersion: ([0-9]+)@", $retstr, $m);
                $ledger = @trim($m[1]);

                if(preg_match("@[ \t]{2,}fee:[^0-9]*([0-9]+\.[0-9]*)@", $retstr, $f)){
                    $fee = ((float)$f[1])*1000*1000;
                }

                /* - - - - - - - - - - - - - - - - - - - - - - - - */

                /**
                 * SEND ABLY NOTIFICATION
                 **/
                $channel = substr(md5($req['user'].$req['to_wallet']),0,20);

                $postdata = [ 'name' => 'withdrawal', 'data' => json_encode([
                    'txInsertId' => $req['id'],
                    'amount' => (float) $req['amount'],
                    'user' => $req['user'],
                    'transaction' => [
                        'ledger' => $ledger,
                        'txid' => $txid,
                    ],
                ])];

                $context = @stream_context_create([ 'http' => [ 'method' => 'POST', 'header' => 'Content-type: application/x-www-form-urlencoded', 'content' => http_build_query($postdata) ] ]);
                $result = @file_get_contents('https://'.$__ABLY_CREDENTIAL.'@rest.ably.io/channels/'.$channel.'/messages', false, $context);
                /**
                 * END -- SEND ABLY NOTIFICATION
                 **/

                /* - - - - - - - - - - - - - - - - - - - - - - - - */
            }

            $query = $db->prepare('UPDATE `withdraw` SET `tx` = :txid, `ledger` = :ledger, `fee` = :fee WHERE `id` = :id LIMIT 1');
            $query->bindValue(':ledger', $ledger);
            $query->bindValue(':txid', $txid);
            $query->bindValue(':id', $req['id']);
            $query->bindValue(':fee', $fee);
            $query->execute();
        }
    }
    else {
        echo "\nNothing to process...\n";
    }
}
catch (\Throwable $e) {
    echo "\n ERROR: " . $e->getMessage() . "\n";
}
