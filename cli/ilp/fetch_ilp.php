<?php

require_once '/data/config.php';
require_once '/data/db.php';

echo "\nProcessing ILP messages...\n";

$tsfile = './ilp-ts.txt';
$ts = @file_get_contents($tsfile);
if (empty($ts)) {
  $ts = '2018-01-31T16:13:32.837Z';
}

$url = "https://api.strata-ilsp.com/?timestamp=" . trim($ts);
echo $url . PHP_EOL;
$data = @file_get_contents($url);

if (!empty($data)) {
  $data = json_decode($data);

  if (!empty($data->timestamp) && !empty($data->payments)) {
    if (@file_put_contents($tsfile, (string) $data->timestamp)) {
      foreach ((array) $data->payments as $r) {
        $r = (array) $r;
        if (preg_match("@^\\$([a-z]+)\/(.+)@", $r['paymentPointer'], $m)) {
          list($paymentPointer, $network, $user) = $m;
          if (in_array(strtolower($network), [ 'twitter', 'reddit', 'discord' ])) {
            $amount = floor($r['sum']);
            echo PHP_EOL . " $network -- $user -- " . $amount;
            $json_data = json_encode(array(
              'totalDrops' => $amount,
              'network' => $network,
              'username' => $user,
              'connectionTag' => $r['paymentPointer'],
              'strata_id' => $r['paymentPointer'],
              'sourceAccount' => 'xxxx',
              'destinationAccount' => 'xxxx',
              'sharedSecret' => 'xxxx',
            ));
            $post = @file_get_contents('http://127.0.0.1/index.php/ilpdeposit', null, stream_context_create(array(
              'http' => array(
                  'protocol_version' => 1.1,
                  'user_agent'       => 'ILP',
                  'method'           => 'POST',
                  'header'           => "Content-type: application/json\r\n".
                                        "Connection: close\r\n" .
                                        "Content-length: " . strlen($json_data) . "\r\n",
                  'content'          => $json_data,
              ),
            )));
            echo " ----- ";
            print_r($post);
            // exit;
          }
        }
      }
    }
  }
}
echo PHP_EOL;
echo PHP_EOL;
// @unlink($tsfile);
