<?php

$originalPostdata = (array) $o_postdata;
$response = [];

if(!empty($o_postdata) && is_object($o_postdata)){
    $insertId = 0;

    try {
        if (!empty($o_postdata->name) && !empty($o_postdata->type)) {
            if ($o_postdata->type == 'twitter') {
                $query = $db->prepare("
                    SELECT 
                        l.`username` u,
                        l.`balance` b
                    FROM 
                        `user` l 
                    WHERE 
                        l.`username` != :name
                        AND l.`balance` > 0
                        AND l.`userid` = (SELECT `userid` FROM `user` r WHERE `username` = :name AND r.`network` = 'twitter')
                        AND l.`network` = 'twitter'
                ");
                $query->bindParam(':name', $o_postdata->name);
                $query->execute();
                $migrations = $query->fetchAll(PDO::FETCH_ASSOC);
                $migrationResult = [];
                if (!empty($migrations)) {
                    foreach($migrations as $m) {
                        $m = (array) $m;
                        $query = $db->prepare('UPDATE `user` SET `balance` = `balance` - :amount WHERE `username` = :name AND `network` = "twitter" LIMIT 1');
                        $query->bindParam(':name', $m['u']);
                        $query->bindParam(':amount', $m['b']);
                        $mr = $query->execute();
                        $migrationResult[$m['u']] = $mr ? 'OK' : $query->errorInfo();
                        if ($mr) {
                            $query = $db->prepare('UPDATE `user` SET `balance` = `balance` + :amount WHERE `username` = :name AND `network` = "twitter" LIMIT 1');
                            $query->bindParam(':name', $o_postdata->name);
                            $query->bindParam(':amount', $m['b']);
                            $mr = $query->execute();    
                        }
                    }
                }
            }
        }            

        $json = [
            'postdata' => $originalPostdata,
            'response' => $response,
            'migrations' => empty($migrations) ? [] : $migrations,
            'migrationResult' => empty($migrationResult) ? [] : $migrationResult
        ];
    }
    catch (\Exception $e) {
        $json = [
            'error' => $e->getMessage()
        ];
    }
    catch (\Throwable $e) {
        $json = [
            'error' => $e->getMessage()
        ];
    }
}