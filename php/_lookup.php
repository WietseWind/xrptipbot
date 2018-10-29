<?php

if(!empty($o_postdata) && is_object($o_postdata)){
    try {
        $json['lookup'] = (string) @$o_postdata->query;
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
                `user`.rejecttips IS NULL 
                AND 
                (
                    (`user`.`network` != "discord" AND `user`.`username` LIKE :wildcard)
                    OR
                    (`user`.`network` = "discord" AND `user`.`userid` LIKE :wildcard)
                )
            GROUP BY `user`.`destination_tag`
            ORDER BY 
                `levenshtein` * COUNT(`tip`.`id`) DESC
            LIMIT 20
        ');
        $query->bindParam(':lookup', $json['lookup']);
        $query->bindParam(':wildcard', $json['wildcard']);
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