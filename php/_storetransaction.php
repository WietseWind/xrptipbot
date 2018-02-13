<?php

$doNotProcess = false;

if(!empty($o_postdata) && is_object($o_postdata)){
    $insertId = 0;

    // $o_postdata->type == EscrowCreate / EscrowFinish
    // $o_postdata->fullTx

    try {
        if (empty($o_postdata->type) || $o_postdata->type == 'Payment') {
            $query = $db->prepare('
                INSERT IGNORE INTO `transaction`
                    (`hash`, `ledger`, `from`, `to`, `xrp`, `tag`)
                VALUES
                    (:hash, :ledger, :from, :to, :xrp, :tag)
            ');
            $query->bindParam(':hash', $o_postdata->hash);
            $query->bindParam(':ledger', $o_postdata->ledger);
            $query->bindParam(':from', $o_postdata->from);
            $query->bindParam(':to', $o_postdata->to);
            $query->bindParam(':xrp', $o_postdata->xrp);
            $query->bindParam(':tag', $o_postdata->tag);

            $query->execute();
            $insertId = (int) @$db->lastInsertId();
        } else {
            if (!empty($o_postdata->type)) {
                if ($o_postdata->type == 'EscrowCreate') {
                    $doNotProcess = true;

                    $query = $db->prepare('
                        INSERT INTO `escrow` (
                            `type`, `hash`, `ledger`, `from`, `to`, `xrp`, `tag`, `sequence`, `date`, `cancel`)
                        VALUES (
                            "create", :hash, :ledger, :from, :to, :xrp, :tag, :sequence, :date, :cancel
                        )
                    ');
                    $query->bindValue(':hash', @$o_postdata->fullTx->hash);
                    $query->bindValue(':ledger', @$o_postdata->fullTx->inLedger);
                    $query->bindValue(':from', @$o_postdata->fullTx->Account);
                    $query->bindValue(':to', @$o_postdata->fullTx->Destination);
                    $query->bindValue(':xrp', @$o_postdata->fullTx->Amount / 1000 / 1000);
                    $query->bindValue(':tag', @$o_postdata->fullTx->DestinationTag);
                    $query->bindValue(':sequence', @$o_postdata->fullTx->Sequence);
                    $query->bindValue(':date', @$o_postdata->fullTx->date);
                    $query->bindValue(':cancel', @$o_postdata->fullTx->CancelAfter);
                }
                if ($o_postdata->type == 'EscrowFinish') {
                    $query = $db->prepare('
                        INSERT INTO `escrow` (
                            `type`, `hash`, `ledger`, `from`, `sequence`, `offer`, `date`)
                        VALUES (
                            "finish", :hash, :ledger, :from, :sequence, :offer, :date
                        )
                    ');
                    $query->bindValue(':hash', @$o_postdata->fullTx->hash);
                    $query->bindValue(':ledger', @$o_postdata->fullTx->inLedger);
                    $query->bindValue(':from', @$o_postdata->fullTx->Account);
                    $query->bindValue(':sequence', @$o_postdata->fullTx->Sequence);
                    $query->bindValue(':offer', @$o_postdata->fullTx->OfferSequence);
                    $query->bindValue(':date', @$o_postdata->fullTx->date);
                }
                $query->execute();
                $insertId = (int) @$db->lastInsertId();

                if ($insertId > 0) {
                    $query = $db->prepare('SELECT * FROM `escrow` WHERE `sequence` = :sequence AND `from` = :from ORDER BY `id` DESC LIMIT 1');
                    $query->bindParam(':from', @$o_postdata->fullTx->Account);
                    $query->bindParam(':sequence', @$o_postdata->fullTx->OfferSequence);
                    $query->execute();
                    $fakeTxData = $query->fetch(PDO::FETCH_ASSOC);

                    // Try to process by looking for the original offer and faking the postdata;
                    // this way the __processdeposit-script will continue as if it's a regular tx
                    $o_postdata = (object) $fakeTxData;
                    $o_postdata->type = 'escrow';
                }
            }
        }

        $json = [
            'storedTransaction' => $insertId > 0 ? $insertId : false,
        ];

        // if(!empty($o_postdata->type) && $o_postdata->type !== 'Payment') {
        //     $json['postdata'] = $o_postdata;
        // }

        if($insertId > 0 && !$doNotProcess /*EscrowCreate, Offer*/) { // If > 0: last Insert ID, else: INSERT *IGNORE*, had this one already
            // Todo: REMOVE 1 == 1 for debug {DEVDEVDEV}
            if(in_array($o_postdata->to, $__WALLETS)){
                // Find user, add to balance
                if((int) $o_postdata->tag > 0 ){
                    include_once '__processdeposit.php';
                }
            }
        }
    }
    catch (\Throwable $e) {
        $json = [
            'storedTransaction' => -1,
            'error' => true
        ];
        // TODO: remove debug info {DEVDEVDEV}
        // $json['processDeposit'] = $e->getMessage();
    }
}