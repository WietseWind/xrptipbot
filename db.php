<?php

    include_once 'config.php';

    try {
      $db = new PDO('mysql:host=localhost;dbname=tipbot', $__DATABASE['user'], $__DATABASE['pass'], array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
    }
    catch (\Throwable $e) {
        // print_r($e);
    }
