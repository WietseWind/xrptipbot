<?php

$originalPostdata = (array) $o_postdata;
$storedBtnTip = 0;
$response = [];

if(!empty($o_postdata) && is_object($o_postdata)){
    $insertId = 0;

    try {
        if (!empty($o_postdata->from) && !empty($o_postdata->to) && !empty($o_postdata->amount) && !empty($o_postdata->from->user) && !empty($o_postdata->from->network) && !empty($o_postdata->to->user) && !empty($o_postdata->to->network)) {
            $response['networkFrom'] = $networkFrom = preg_replace('@[^a-z]*@', '', $o_postdata->from->network);
            $response['networkTo'] = $networkTo = preg_replace('@[^a-z]*@', '', $o_postdata->to->network);
            $response['userFrom'] = $userFrom = preg_replace("@['\"\r\n]*@", '', $o_postdata->from->user);
            $response['userTo'] = $userTo = preg_replace("@['\"\r\n]*@", '', $o_postdata->to->user);
            $response['amount'] = $amount = (float) $o_postdata->amount;
            
            if(!empty($o_postdata->app)){
                if ($amount < 0.000001) {
                    $amount = 0.000001;
                }
                if ($amount > 20) {
                    $amount = 20;
                }
            } else{
                if ($amount < 0.01) {
                    $amount = 0.01;
                }
                if (!isset($o_postdata->noLimit) && $amount > 5) {
                    $amount = 5;
                }
            }

            $toField = $fromField = 'username';

            if ($networkFrom === 'discord' && !preg_match('@^\d+$@', $userFrom)) {
                // Resolve from-user from username to userid
                // Search in discord "userid" and get "username"
                $query = $db->prepare('SELECT username FROM user WHERE network = "discord" AND userid = :u');
                $query->bindValue(':u', $userFrom);
                $query->execute();
                $duser = $query->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($duser)) {
                    $userFrom = $duser[0]['username'];
                }
            }
            if ($networkTo === 'discord' && !preg_match('@^\d+$@', $userTo)) {
                // Resolve from-user from username to userid
                // Search in discord "userid" and get "username"
                $query = $db->prepare('SELECT username FROM user WHERE network = "discord" AND userid = :u');
                $query->bindValue(':u', $userTo);
                $query->execute();
                $duser = $query->fetchAll(PDO::FETCH_ASSOC);
                // print_r($duser);
                if (!empty($duser)) {
                    $userTo = $duser[0]['username'];
                }
            }
            // if ($networkFrom == 'discord') $fromField = 'userid';
            // if ($networkTo == 'discord') $toField = 'userid';
            $query = $db->prepare('
                SELECT *, "from" as type FROM `user` WHERE `network` = "'.$networkFrom.'" and '.$fromField.' = "'.$userFrom.'" 
                UNION 
                SELECT *, "to" as type FROM `user` WHERE `network` = "'.$networkTo.'" and '.$toField.' = "'.$userTo.'"
            ');
            $query->execute();
            $usrs = $query->fetchAll(PDO::FETCH_ASSOC);
            $response['userLookup'] = $usrs;
            $disabledUser = false;
            if(!empty($usrs)) {
                $ufrom = [];
                $uto = [];
                foreach ($usrs as $u) {
                    if (!empty($u['rejecttips'])) {
                        $disabledUser = true;
                    }
                    if($u['type'] === 'from') {
                        $response['ufrom'] = $ufrom = $u;
                    }else{
                        $response['uto'] = $uto = $u;
                    }
                }
            }
        
            if (!empty($ufrom) && $amount > (float) $ufrom['balance']) {
                $amount = (float) $ufrom['balance'];
            }
        
            if (empty($ufrom)) {
                $msg = 'Register your Tip Bot account first, visit https://www.xrptipbot.com/?login=discord and deposit some XRP.';
            } elseif($disabledUser) {
                $msg = 'User disabled';
            } elseif( (float) $ufrom['balance'] == 0) {
                $msg = "You don't have any XRP in your Tip Bot account, deposit at https://www.xrptipbot.com/deposit";
            } else {
                if(empty($uto)){
                    // Create TO user
                    $query = $db->prepare('INSERT IGNORE INTO user (username, create_reason, network) VALUES (:username, "TIPPED", :network)');
                    $query->bindValue(':username', $userTo);
                    $query->bindValue(':network', $networkTo);
                    $query->execute();
                    $uto['balance'] = 0;
                    $response['toUserCreated'] = true;
                }
        
                $msg = 'tipped **' . $amount . ' XRP** to <@' . $userTo . '> :tada:';
        
                $query = $db->prepare('INSERT IGNORE INTO `tip`
                                        (`amount`, `from_user`, `to_user`, `sender_balance`, `recipient_balance`, `network`, `context`, `from_network`, `to_network`)
                                            VALUES
                                        (:amount, :from, :to, :senderbalance, :recipientbalance, :method, :context, :fromnetwork, :tonetwork)
                ');
        
                $context = @$o_postdata->url .' ### '. $userFrom . '[' . $networkFrom . '] => ' . $userTo . '[' . $networkTo . ']';
                $method = 'btn';
                if(!empty($o_postdata->app)){
                    $method = 'app';
                }

                $query->bindValue(':method', $method);
                $query->bindValue(':amount', $amount);
                $query->bindValue(':from', $ufrom['username']);
                $query->bindValue(':to', $userTo);
                $query->bindValue(':senderbalance', - $amount);
                $query->bindValue(':recipientbalance', + $amount);
                $query->bindValue(':context', $context);
                $query->bindValue(':fromnetwork', $networkFrom);
                $query->bindValue(':tonetwork', $networkTo);
        
                $query->execute();
        
                $insertId = (int) @$db->lastInsertId();
                $is_valid_tip = true;
        
                if(!empty($insertId)) {        
                    $query = $db->prepare('UPDATE `user` SET `balance` = `balance` - :amount WHERE '.$fromField.' = :from AND `network` = :network LIMIT 1');
                    $query->bindValue(':amount', $amount);
                    $query->bindValue(':network', $ufrom['network']);
                    $query->bindValue(':from', $ufrom['username']);
                    $query->execute();
        
                    $query = $db->prepare('UPDATE `user` SET `balance` = `balance` + :amount WHERE '.$toField.' = :to AND `network` = :network LIMIT 1');
                    $query->bindValue(':amount', $amount);
                    $query->bindValue(':network', $networkTo);
                    $query->bindValue(':to', $userTo);
                    $query->execute();
                    $storedBtnTip = 1;
                }
        
            }
            $response['message'] = $msg;
        }            

        $json = [
            // 'postdata' => $originalPostdata,
            'storedBtnTip' => $storedBtnTip,
            'response' => $response
        ];
    }
    catch (\Exception $e) {
        $json = [
            'storedBtnTip' => -1,
            'error' => $e->getMessage()
        ];
    }
    catch (\Throwable $e) {
        $json = [
            'storedBtnTip' => -1,
            'error' => $e->getMessage()
        ];
    }
}
