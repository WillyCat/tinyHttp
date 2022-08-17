<?php

require_once 'tinyHttp.class.php';

$url = 'http://localhost/301.php';
$url = 'http://www.google.com';

$http = new tinyHttp ($url);
$http->setConfig('follow_redirects',true);
$response = $http->send();
echo 'URL: ' . $http -> getUrl() . "\n";
echo 'Status: ' . $response -> getStatus() . ' - ' . $response -> getReasonPhrase() . "\n";
echo 'Protocol: ' . $http -> getScheme() . "\n";
echo 'Headers: ' . "\n";
$headers = $response -> getHeaders();
foreach ($headers as $name => $value)
	echo "\t" . $name.': ' . $value . "\n";
echo 'Content-Length: ' . $response -> getContentLength() . "\n";
echo 'Text length: ' . strlen($response->getBody()) .  "\n";
echo '-----------------------------------------------------------' . "\n";
/*
$h = new tinyHttp ($url);
$h->setConfig([ 'follow_redirects' => true ]);
$h -> send();
echo $h;
echo '-----------------------------------------------------------' . "\n";
*/

?>
