<?php

use Rudolf\OAuth2\Client\Provider\Reddit;
use League\OAuth2\Client\Token\AccessToken;

include_once 'vendor/autoload.php';
require_once '/data/config.php';

$reddit = new Reddit($__REDDIT_CLIENT_CONFIG);

// identity%2Cedit%2Chistory%2Cmysubreddits%2Cprivatemessages%2Cread%2Creport%2Csave%2Csubmit
// ^^ use this perms in the URL

// 1. Request URL
// $url = $reddit->getAuthorizationUrl([
//     'duration' => 'permanent',
// ]);
// echo "\n\n";
// print_r($url);
// echo "\n\n";
// exit;
//
// 2. Set Response
// $authUrlResponse = [
//     'code'  => 'xxxxxx',
//     'state' => 'yyyyy',
// ];
//
// 3. Request token + Save
// echo "Requesting new token";
// $accessToken = $accessToken = $reddit->getAccessToken('authorization_code', $authUrlResponse);
// @file_put_contents('oauth2token.json', json_encode($accessToken));
// echo "\n";
// exit;

$accessToken = (array) @json_decode(trim(@file_get_contents('oauth2token.json')));
if(empty($accessToken) || empty($accessToken['expires']) || (int) $accessToken['expires'] < date('U') || 1==1){
    // Refresh token
    $refreshToken = $reddit->getAccessToken('refresh_token', [
        'refresh_token' => $accessToken['refresh_token']
    ]);

    $refreshToken = (array) @json_decode(@json_encode($refreshToken));

    $accessToken['access_token'] = $refreshToken['access_token'];
    if(!empty($refreshToken->refreshToken)) $accessToken['refresh_token'] = $refreshToken['refresh_token'];
    $accessToken['expires'] = $refreshToken['expires'];
    @file_put_contents('oauth2token.json', json_encode($accessToken));
}

$accessToken = @new AccessToken($accessToken);

if(!function_exists('reddit_call')){
    $reddit_call = function ($url = '/api/v1/me', $method = 'GET', $data = []) use ($reddit, $accessToken, $__REDDIT_USER_AGENT) {
        $headers = $reddit->getHeaders($accessToken);
        $headers['User-Agent'] = $__REDDIT_USER_AGENT;

        $header = '';
        foreach($headers as $k=>$v){
            $header .= $k.': '.$v."\r\n";
        }

        if(is_array($data)) $postdata = http_build_query($data);

        $opts = [ 'http' => [ 'method' => strtoupper($method), 'header' => $header, 'content' => $postdata ] ];
        if($opts['http']['method'] == 'GET') unset($opts['http']['content']);
        // print_r($opts);

        $result = @file_get_contents('https://oauth.reddit.com'.$url, false, stream_context_create($opts));

        return @json_decode($result);
    };
}
