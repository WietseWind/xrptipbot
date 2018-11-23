<?php

require_once '/data/db.php';

try {
    $query = $db->prepare('
        SELECT 
            *, 
            (UNIX_TIMESTAMP() - 946684800) AS currts
        FROM 
            `withdraw`
        WHERE 
            `escrowts` IS NOT NULL 
            AND `ledger` > 0 
            AND `escrowts` < (UNIX_TIMESTAMP() - 946684800 - 60) /* Wait one minute, allow someone else to try */
            AND `escrow_release_hash` IS NULL
        LIMIT 1
    ');

    $query->execute();
    $req = $query->fetch(PDO::FETCH_ASSOC);
    if(!empty($req)){
        echo "\n Processing (escrow release): \n";
        preg_match("@[^a-zA-Z]Sequence: ([0-9]+)@", $req['log'], $m);
        if ($m) {
            $seq = $m[1];
        }

        $query = $db->prepare('UPDATE `withdraw` SET `escrow_release_hash` = "X" WHERE `id` = :id LIMIT 1');
        $query->bindValue(':id', $req['id']);
        $query->execute();
        
        if (empty($seq)) {
            echo "\nCannot find sequence for: \n";
            print_r($req);
            echo "\n";
            exit;
        }

        $att = $req['from_wallet'].':'.$req['to_wallet'].':'.(@strtoupper(@bin2hex(@utf8_decode($req['memo'])))).':'.$seq;
        echo "Processing...";

        $md = md5($att);
        @exec("cd /data/nodejs/sendwithdrawreq; nodejs release.js $att 2>&1 > app.log.$md ; cat app.log.$md; cat app.log.$md > app.log; rm app.log.$md;", $retArr, $retVal);
        echo "Done;\n";

        $retstr = trim(implode("\n", $retArr));
        echo $retstr."\n";

        $query = $db->prepare('UPDATE `withdraw` SET `log` = CONCAT(`log`, "     _____ ESCROW RELEASE _____ ", :log) WHERE `id` = :id LIMIT 1');
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

                // /**
                //  * SEND ABLY NOTIFICATION
                //  **/
                // $channel = substr(md5($req['user'].$req['to_wallet']),0,20);

                // $postdata = [ 'name' => 'withdrawal', 'data' => json_encode([
                //     'txInsertId' => $req['id'],
                //     'amount' => (float) $req['amount'],
                //     'user' => $req['user'],
                //     'transaction' => [
                //         'ledger' => $ledger,
                //         'txid' => $txid,
                //     ],
                // ])];

                // $context = @stream_context_create([ 'http' => [ 'method' => 'POST', 'header' => 'Content-type: application/x-www-form-urlencoded', 'content' => http_build_query($postdata) ] ]);
                // $result = @file_get_contents('https://'.$__ABLY_CREDENTIAL.'@rest.ably.io/channels/'.$channel.'/messages', false, $context);
                // /**
                //  * END -- SEND ABLY NOTIFICATION
                //  **/

                /* - - - - - - - - - - - - - - - - - - - - - - - - */
            }

            if (empty($fee)) {
                $fee = 0;
            }

            $query = $db->prepare('UPDATE `withdraw` SET `escrow_release_hash` = :txid, `fee` = (`fee` + :fee) WHERE `id` = :id LIMIT 1');
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
