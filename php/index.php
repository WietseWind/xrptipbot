<?php

header('Content-type: application/json; Charset=UTF-8');

include_once '../config.php';
include_once '../db.php';

$o_postdata = [];
$s_postdata = @file_get_contents('php://input');
if(is_string($s_postdata) && !empty($s_postdata)){
    $j_postdata = @json_decode($s_postdata);
    if(!empty($j_postdata)){
        $o_postdata = (object) $j_postdata;
    }
}

if(isset($_SERVER['PATH_INFO'])){
    $s_path = preg_replace("@^\/+@", '', $_SERVER['PATH_INFO']);
    $a_path = explode('/', $s_path);
    if(preg_match('@^[a-z]+$@', $a_path[0])){
        $module = '_'.$a_path[0].'.php';
        if(file_exists($module)){
            include_once $module;
        }
    }
}

if(isset($json)){
    echo json_encode($json);
}else {
    echo json_encode([ 'error' => true ]);
}
