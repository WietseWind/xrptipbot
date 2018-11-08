<?php

if(!empty($o_postdata) && is_object($o_postdata)){
    try {
        $json['lookup'] = (string) @$o_postdata->query;
        if (preg_match("@rPEPPER7kfTD9w2To4CQk6UCfuHM9c6GDY.+dt=([0-9]+)@", $json['lookup'], $m)) {
            $query = $db->prepare('
                SELECT 
                    IF(`user`.`network` != "discord", `user`.username, `user`.userid) as slug,
                    `user`.`network`,
                    `user`.username,
                    `user`.userid,
                    `user`.last_login, 
                    `user`.destination_wallet, 
                    IF(`user`.public_destination_tag IS NULL, `user`.destination_tag, `user`.public_destination_tag) as destination_tag,
                    COUNT(`tip`.`id`) as tipcount,
                    0 as levenshtein
                FROM 
                    `user`
                LEFT JOIN
                    `tip` ON (
                        `user`.`username` = `tip`.`from_user` 
                        AND
                        `user`.`network` = `tip`.`network` 
                    )
                WHERE 
                    `user`.public_destination_tag = :tag
                    AND `user`.`network` != "internal"
                    -- OR
                    -- `user`.destination_tag = :tag
                GROUP BY `user`.`destination_tag`
                LIMIT 20
            ');
            $query->bindParam(':tag', $m[1]);
        } else {
            $json['wildcard'] = '';
            for ($i=0;$i<strlen($json['lookup']);$i++) {
                $json['wildcard'] .= $json['lookup']{$i}.'%';
            }
            $query = $db->prepare('
                SELECT 
                    IF(`user`.`network` != "discord", `user`.username, `user`.userid) as slug,
                    `user`.`network`,
                    `user`.username,
                    `user`.userid,
                    `user`.last_login, 
                    `user`.destination_wallet, 
                    IF(`user`.public_destination_tag IS NULL, `user`.destination_tag, `user`.public_destination_tag) as destination_tag,
                    COUNT(`tip`.`id`) as tipcount,
                    levenshtein(:lookup, IF(`user`.`network` != "discord", `user`.username, `user`.userid)) as levenshtein
                FROM 
                    `user`
                LEFT JOIN
                    `tip` ON (
                        `user`.`username` = `tip`.`from_user` 
                        AND
                        `user`.`network` = `tip`.`network` 
                    )
                WHERE 
                    `user`.last_login IS NOT NULL 
                    AND 
                    `user`.`network` != "internal"
                    AND 
                    `user`.rejecttips IS NULL 
                    AND 
                    (
                        (`user`.`network` != "discord" AND `user`.`username` LIKE :wildcard)
                        OR
                        (`user`.`network` = "discord" AND (`user`.`userid` LIKE :wildcard OR `user`.`username` LIKE :lookup))
                    )
                GROUP BY `user`.`destination_tag`
                ORDER BY 
                    `levenshtein` * COUNT(`tip`.`id`) DESC
                LIMIT 20
            ');
            $query->bindParam(':lookup', $json['lookup']);
            $query->bindParam(':wildcard', $json['wildcard']);
        }
        $query->execute();
        $users = $query->fetchAll(PDO::FETCH_ASSOC);
        $json['output'] = @$users;
    }
    catch (\Throwable $e) {
        $json = [
            'error' => true
        ];
    }
}