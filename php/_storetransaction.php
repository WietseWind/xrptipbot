<?php

if(!empty($o_postdata) && is_object($o_postdata)){
    try {
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

        $json = [
            'storedTransaction' => $insertId > 0 ? $insertId : false
        ];

        if($insertId > 0) { // If > 0: last Insert ID, else: INSERT *IGNORE*, had this one already
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