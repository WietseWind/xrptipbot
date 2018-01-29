<?php

use Abraham\TwitterOAuth\TwitterOAuth;

include_once 'vendor/autoload.php';
require_once '/data/config.php';

if(!function_exists('twitter_call')){
    $twitter_call = function ($url = 'statuses/home_timeline', $method = 'GET', $data = [], $json_data = []) use ($__TWITTER_CLIENT_CONFIG) {
        $connection = new TwitterOAuth(
            $__TWITTER_CLIENT_CONFIG['consumerKey'],
            $__TWITTER_CLIENT_CONFIG['consumerSecret'],
            $__TWITTER_CLIENT_CONFIG['accessToken'],
            $__TWITTER_CLIENT_CONFIG['accessTokenSecret']
        );

        $method = strtolower($method);
        return $connection->$method($url, $data, $json_data);
    };
}
