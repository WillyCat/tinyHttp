# tinyHttp
minimal http class using only native php functions
whenever possible, interface mimics pear http_request2
this class is mainly a wrapper around file_get_contents() so it will behave just as file_get_contents does

usage:
try {
$h = new tinyHttp ('http://www.site.com');
$h -> getUrl() // http://www.site.com
$h -> getScheme() // string (http)
$h -> getHost() // string (www.site.com)
$h -> getMethod() // string (get)
$h -> setConfig ('follow_redirects', true);
$h -> setConfig ('max_redirects', 10);
$h -> setHeader ('Accept', 'application/vnd.discogs.v2.html+json');
$r = $h -> send();
$r -> getStatus() // 200
$r -> getHeaders() // array of code:value
$r -> getHeader('Content-Length') // header value or null
$r -> getContentLength() // Content-Length value or 0
$r -> getBody() // string, can be empty
} catch (tinyHttp_Exception $e) {
  $e -> getMessage()
}

$h = new tinyHttp();
$h -> setUrl('http://www.site.com');

$h = new tinyHttp ('http://www.site.com', tinyHttp::METHOD_GET);

class implements __toString() :
echo new tinyHttp('http://www.site.com');
will display the content of the page behind the url

$h = new tinyHttp();
$h -> setMethod('POST');
$h -> setPostValues ([ 'q' => 'xxx', 'login' => $login ]);
$h -> send();
