# tinyHttp

tinyHttp is a simple PHP library to run a http client

whenever possible, interface mimics pear http_request2

this class does NOT use cURL or any third party librairies but is based on file_get_contents() - this are pros and cons on this option, this depends on each one's use case.

## Installation

Until version 1.19, all classes were included in a single file.
Starting with 1.20, each class has its own file.

## Usage

### tinyUrl

Analyze an URL
```php
$u = new tinyUrl ('http://www.example.com:8080/index.html?x=A&y=B');
$u -> getScheme() ==> 'http'
$u -> getHost()     ==> 'www.example.com'
$u -> getPort()     ==> 8080
$u -> getPath()     ==> '/index.html'
$u -> getQuery()    ==> 'x=A&y=B'
```

Build an URL
```php
 $u = new tinyUrl ();
 $u -> setScheme ('https');
 $u -> setHost ('www.example.ccom');
 $u -> setPort (8080);
 $u -> setPath ('/index.html');
 $u -> setQuery([ 'x' => 'A', 'y' => 'B' ])
 $u -> getUrl() => 'https://www.example.com:8080/index.html?x=A&y=B'
```

Mixed
```php
$u = new tinyUrl ();
$u -> setUrl ('http://www.example.com/index.html');
$u -> getScheme() ==> 'http'
$u -> setUser ('john');
$u -> setPass ('secret');
$u -> setQuery([ 'x' => 'A', 'y' => 'B' ])
$u -> getUrl() => 'http://john:secret@www.example.com/index.html?x=A&y=B'
echo $u => http://john:secret@www.example.com/index.html?x=A&y=B
```

### tinyHttp

Basic usage
```php
echo new tinyHttp('http://www.site.com');
 ```
 
 Detailed usage
```php
 try {
 $h = new tinyHttp ('http://www.site.com');
 $h -> getUrl() // http://www.site.com
 $h -> getScheme() // string (http)
 $h -> getHost() // string (www.site.com)
 $h -> getMethod() // string (get)
 $h -> setConfig ('follow_redirects', true);
 $h -> setConfig ('max_redirects', 10);
 $h -> setHeader ('Accept', 'application/vnd.discogs.v2.html+json');
 $h -> setCookie ($cookie);
 $h -> setCallback ($callback);
 $r = $h -> send();
 $r -> getStatus() // 200
 $r -> getHeaders() // array of code:value
 $r -> getHeader('Content-Length') // header value or null
 $r -> getContentLength() // Content-Length value or 0
 $r -> getBody() // string, can be empty
 } catch (tinyHttp_Exception $e) {
   $e -> getMessage()
 }
 ```
 
