# tinyHttp

tinyHttp is a simple PHP library to handle http requests

## Installation

All classes are included in a single file.

## Usage

```php
echo new tinyHttp('http://www.site.com');
 ```
 
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
 
