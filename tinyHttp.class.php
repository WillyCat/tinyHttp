<?php
/**
 * @package tinyHttp
 * @version $Revision: 1.20 $
 *
 * minimal http class using only native php functions
 * whenever possible, interface mimics pear http_request2
 * this class is mainly a wrapper around file_get_contents() so it will behave just as file_get_contents does
 *
 * usage:
 *
 * try {
 * $h = new tinyHttp ('http://www.site.com');
 * $h -> getUrl() // http://www.site.com
 * $h -> getScheme() // string (http)
 * $h -> getHost() // string (www.site.com)
 * $h -> getMethod() // string (get)
 * $h -> setConfig ('follow_redirects', true);
 * $h -> setConfig ('max_redirects', 10);
 * $h -> setHeader ('Accept', 'application/vnd.discogs.v2.html+json');
 * $h -> setCookie ($cookie);
 * $h -> setCallback ($callback);
 * $r = $h -> send();
 * $r -> getStatus() // 200
 * $r -> getHeaders() // array of code:value
 * $r -> getHeader('Content-Length') // header value or null
 * $r -> getContentLength() // Content-Length value or 0
 * $r -> getBody() // string, can be empty
 * } catch (tinyHttp_Exception $e) {
 *   $e -> getMessage()
 * }
 *
 * $h = new tinyHttp();
 * $h -> setUrl('http://www.site.com');
 *
 * $h = new tinyHttp ('http://www.site.com', tinyHttp::METHOD_GET);
 *
 * class implements __toString() :
 * echo new tinyHttp('http://www.site.com');
 * will display the content of the page behind the url
 *
 * $h = new tinyHttp();
 * $h -> setMethod('POST');
 * $h -> setPostValues ([ 'q' => 'xxx', 'login' => $login ]);
 * $h -> send();
 *
 *
 * Date        Ver  Change
 * ----------  ---  -------------------------------------------------------
 *             0.1  Started (partial)
 *             0.2 
 *             0.3  splitted tinyHttp introducing tinyHttpReponse
 *             1.0  First working version
 * 2018-10-09  1.1  new methods: tinyHttp::removeHeader(), tinyHttp::setDebugLevel()
 * 2018-10-19  1.1a added missing prototypes
 * 2018-11-27  1.2  new methods: tinyHttp::getHeaders(), tinyHttp::getVersion()
 *                  added 'Host:' header (mandatory in HTTP 1.1)
 *                  moved url analysis from send() to setUrl()
 *                  setUrl can now throw an exception if url is not correct
 *                  tinyHttp::port set from url when provided
 * 2018-12-17  1.3  - new methods: resetHeaders(), setHeader()
 *                  - when Content-Length header is not provided, getContentLength() returned null, now returns text length
 * 2018-12-26  1.4                 
 *                  - bugfix: getVersion() was still returning 1.2 (fixed)
 *                  - when using https, checks that openssl is loaded
 *                  - new class tinyUrl
 *                  - new trait tinyDebug, improved debug
 *                  - more http codes
 *                  - renamed "protocol" as "scheme"
 * 2018-12-27  1.5  fixed some broken compatibility issues with previous versions
 * 2019-01-04  1.6  fixed prototype error in tinyUrl::setQuery
 *                  new: tinyUrl::resetQuery(), tinyUrl::addQuery()
 *                  added phpdoc infos for tinyURL (other classes to come)
 * 2019-01-09  1.7  - added third parm to debug() to provide message level
 *                    messages below debug level are dropped 
 *                  - more phpdoc
 *                  - getVersion() now static
 *                  - setDebug() restored for compatibility with prev. releases
 * 2019-01-28  1.8  bugfix: missing tinyUrl::setUrl()
 *                  new: tinyHttp::_construct() accepts a tinyUrl object
 * 2019-04-15  1.9  bugfix: errors in tinyDebug
 * 2019-04-18  1.10  added setCookie()
 *                   per RFC 2616, header names are case insensitive, updated code accordignly
 * 2019-04-22  1.11  as of php 7.2, create_function is deprecated
 * 2019-04-28  1.12  new: tinyHttp::setCallback
 * 2019-05-06  1.13  fixed: tinyHttp::setCookie()
 *                   new: tinyHttp::resetContent(), tinyHttp::appendContent()
 * 2019-05-10  1.14  minor code refactoring
 * 2019-08-05  1.15  tinyClass exported to tinyClass.class.php
 *                   managing header with multiple values (like Set-Cookie)
 * 2019-08-07  1.16  new: tinyUrl::getOrigin()
 * 2019-08-12  1.17  change to comments only
 * 2019-08-19  1.18  tinyUrl::setHost() was documented but not defined
 *                   tinyUrl::addQuery() was buggy
 * 2021-07-10  1.19  extended debug
 * 2022-08-17  1.20  split each class in a separate file
 */

require_once 'tinyhttp_Exception.class.php';
require_once 'tinyHttp_LogicException.class.php';
require_once 'tinyClass.class.php';
require_once 'tinyUrl.class.php';
require_once 'tinyHttpResponse.class.php';

//================================================================
class tinyHttp extends tinyClass
{
	const METHOD_GET  = 'GET';
	const METHOD_POST = 'POST';
	// parms
	private $method;	// METHOD_GET, METHOD_POST
	private $url;           // tinyUrl object
	private $follow_redirects = false;
	private $max_redirects = 10;
	// query
	private $query_headers; // array of name => value
	private $query_content; // string, as sent
	private $response;	// tinyHttpResponse object
	private $callback;

	// scheme must be 'http' or 'https'
	public function
	__construct($url = null, string $method = tinyHttp::METHOD_GET)
	{
		$this -> url = null;
		if ($url != null)
			$this -> setUrl ($url);
		$this -> setMethod ($method);
		$this -> resetHeaders();
		$this -> setContent ('');
		$this -> callback = null;
	}
	private function
	normalize (string $name): string
	{
		$name = trim($name);
		$name = strtolower ($name);
		return $name;
	}
	public function
	setCallback ($callback): void
	{
		$this -> callback = $callback;
	}

	/**
	 * set URL
	 * @param string|tinyUrl $url
	 */
	public function
	setUrl ($url): void
	{
		if ($url instanceof tinyUrl)
			$this -> url = $url;
		else
			$this -> url = new tinyUrl ($url);
	}
	public function
	getUrl (): string
	{
		return $this -> url -> getUrl();
	}
	static public function
	getVersion(): string
	{
		return '1.20';
	}
	public function
	getScheme(): string
	{
		return $this -> url -> getScheme();
	}
	// for compatibility
	public function
	setDebug (int $level): void
	{
		$this -> setDebugLevel ($level);
	}
	//
	// Configuration
	//
	public function
	setConfig ($nameOrConfig, $value = null): void
	{
		 if (is_array ($nameOrConfig))
			foreach ($nameOrConfig as $name => $value)
				$this -> setConfigItem ($name, $value);
		else
			$this -> setConfigItem ($nameOrConfig, $value);
	}
	private function
	setConfigItem (string $name, string $value): void
	{
		switch ($name)
		{
		case 'follow_redirects' : $this -> follow_redirects = $value; break;
		case 'max_redirects' : $this -> max_redirects = $value; break;
		default : throw new tinyHttp_LogicException('unknown parameter: '.$name);
		}
	}
	private function
	buildHeader (): string
	{
		$header = '';
		$hdr = 1;
		foreach ($this -> query_headers as $name => $value)
		{
			$header .= $name . ': ' . $value . "\r\n";
			$this -> debug ('Header #' . $hdr++ . ': ' . $name . ': ' . $value );
		}
		return $header;
	}
	/**
	 *  build the 'http' part of the http_context array, as used by stream_context_create()
	 */
	private function
	buildStreamHttpContext():array
	{
		$http_context = [ ];

		$http_context['method'] = $this -> method;
		// we directly set User-Agent header
		// $http_context['user_agent']
		// $http_context['proxy']
		// $http_context['request_fulluri']
		$http_context['protocol_version'] = 1.1;
		// $http_context['timeout']
		$http_context['follow_location']  = $this -> follow_redirects;
		$http_context['ignore_errors'] = true; // if false, file_get_contents() will return false if 404 - if true, will return data

		$http_context['max_redirects']    = $this -> max_redirects;
		$this -> debug ( "follow redirects: " . ($this -> follow_redirects ? 'Yes' : 'No') );

		$http_context['header'] = $this -> buildHeader();
/*
Accept-Encoding: gzip, deflate, br
Accept-Language: fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3
Cache-Control: max-age=0
Connection: keep-alive
Cookie: _ga=GA1.2.2135724536.151187987.Mb-M-XxntU_m56KegEWdM9qZVshXA
DNT: 1
Upgrade-Insecure-Requests	1
User-Agent: Mozilla/5.0 (Macintosh; Intel .) Gecko/20100101 Firefox/60.0
*/
		$http_context['content'] = $this -> query_content;

		return $http_context;
	}
	/**
	 *  build a http_context array, as used by stream_context_create()
	 */
	private function
	buildStreamContext(): array
	{
		// http refers to the protocol and covers http & https
		return [ 'http' => $this -> buildStreamHttpContext() ];
	}
	//
	// Run !
	//
	public function
	send(): tinyHttpResponse
	{
		// ------------------------------
		// Check args
		// ------------------------------
		if (is_null ($this -> url))
			throw new tinyHttp_Exception ('no valid url provided');
		if (!in_array ($this -> method, [ 'GET', 'POST' ] ))
			throw new tinyHttp_Exception ('method not implemented: ' . $this -> method);

		// ------------------------------
		// Set "Host" HTTP header
		// ------------------------------
		$host = $this -> url -> getHost();
		if (!$this -> url -> isStandardPort()) // port can be omitted if standard
			$host .= ':' . $this -> url -> getPort();
		$this -> setHeader ('Host', $host);

		/*
		Note that if you set the protocol_version option to 1.1 and the server you are requesting from is configured to use keep-alive connections, the function (fopen, file_get_contents, etc.) will "be slow" and take a long time to complete. This is a feature of the HTTP 1.1 protocol you are unlikely to use with stream contexts in PHP.
		Simply add a "Conection: close" header to the request to eliminate the keep-alive timeout:
		*/
		// $this -> setHeader ('Connection', 'Close');

		// ------------------------------
		// Preparing context
		// ------------------------------
		$this -> debug ( "building context..." );
		$context = stream_context_create( $this -> buildStreamContext() );
		if ($this -> callback != null)
			stream_context_set_params($context, ['notification' => $this -> callback]);
		$this -> debug ( "context built" );

		// ------------------------------
		// Preparing tinyHttpResponse
		// ------------------------------
		$this -> debug ( "creating tinyHttpResponse..." );
		$this -> response = new tinyHttpResponse();
		$this -> debug ( "tinyHttpResponse created" );
		$this -> response -> setDebugLevel ($this -> getDebugLevel() );

		// ------------------------------
		// Setting error handler
		// ------------------------------
		// if an error occurs, file_get_contents will not raise an exception
		// but display error message
		// we do not want the message to pollute display but we do want to miss the message
		// to have file_get_contents raise an exception with the error message,
		// we do the following :
		// 1. set an error handler that will catch any error occurring
		// 2. have the error handler raise an exception with the error message
		$this -> debug ( "setting error handler..." );
		set_error_handler(
		    function($severity, $message, $file, $line) {
			throw new ErrorException($message, 0, $severity, $file, $line); });
		$this -> debug ( "error handler set" );

		// ------------------------------
		// Let's go
		// ------------------------------
		try
		{
			$this -> debug ( "Sending request" );
			$this -> debug ( 'url: ' . $this -> url -> getUrl());
			$reqStart = microtime(true);

			$content = file_get_contents ($this -> url -> getUrl(), false, $context);

			$reqEnd = microtime(true);
			$this -> debug ( "Got a response (took " .sprintf ('%.3f',($reqEnd - $reqStart)). " s)" );
			restore_error_handler();
		} catch (Exception $e) {
			restore_error_handler();

			// in case of a time-out,
			// $content is false,
			// $http_response_header is []
			// and getMessage() is 'Failed to open stream: HTTP request failed!'
			// unable to retrieve http code 504
			// or to identify clearly this is a time-out

			$this -> debug ( 'throwing exception: ' . $e -> getMessage());
			throw new tinyHttp_Exception ($e -> getMessage());
		}

		// ------------------------------
		// Check result
		// ------------------------------
		if ($content === false)
		{
			$this -> debug ( 'throwing exception: failure');
			throw new tinyHttp_Exception ('failure');
		}

		// ------------------------------
		// Capture response
		// ------------------------------
		$this -> response -> setContent ($content);
		$this -> response -> setHeaders ($http_response_header);	// $http_response_header is set by file_get_contents()
		return $this -> response;
	}
	//
	// Method
	//
	public function
	getMethod(): string
	{
		return $this -> method;
	}
	public function
	setMethod(string $method): void
	{
		$method = strtoupper($method);
		$this -> debug ( "setting method to " . $method );
		switch ($method)
		{
		case tinyHttp::METHOD_GET :
			break;
		case tinyHttp::METHOD_POST :
			$this -> setContentType ('application/x-www-form-urlencoded');
			break;
		default :
			throw new tinyHttp_Exception ('Invalid method');
		}
		$this -> method = $method;
	}
	//
	// Headers
	//
	public function
	resetHeaders(): void
	{
		$this -> query_headers = [ ];
	}
	public function
	removeHeader (string $name): void
	{
		$name = $this -> normalize($name);
		if (array_key_exists ($name, $this -> query_headers))
			unset ($this -> query_headers[$name]);
	}
	public function
	getHeaders(): array
	{
		return $this -> query_headers;
	}
	/**
	 * Set one or many header(s)
	 *
	 * @param mixed $nameOrArray
	 * @param string $value
	 *
	 * to set a single header:
	 * setHeader ('header', 'value')
	 * to set a group of headers:
	 * setHeader ([ 'header1' => 'value1', 'header2', 'value2' ]);
	 */
	public function
	setHeader ($nameOrArray, string $value = null): void
	{
		if (is_array ($nameOrArray))
			foreach ($nameOrArray as $name => $value)
				$this -> setSingleHeader ($name, $value);
		else
			$this -> setSingleHeader ($nameOrArray, $value);
	}
	private function
	setSingleHeader (string $name, string $value): void
	{
		$name = $this -> normalize($name);
		$this -> debug ( "setting header:  " . $name . ": " . $value );
		$this -> query_headers[$name] = $value;
	}
	// shorthand for Content-Type header
	public function
	setContentType (string $contentType): void
	{
		$this -> setHeader('Content-type', $contentType);
	}
	/**
	 * @param string $userAgent set user agent
	 */
	public function
	setUserAgent (string $userAgent): void
	{
		$this -> setHeader('User-Agent', $userAgent);
	}
	/**
	* @param string $cookie Cookie
	 */
	public function
	setCookie (string $cookie): void
	{
		$this -> setHeader('Cookie', $cookie);
	}
	//
	// Content
	//
	public function
	setPostValues (array $values): void
	{
		$postdata = http_build_query ($values);
		$this -> setContent ($postdata);
	}
	public function
	setContent (string $content): void
	{
		$this -> query_content = $content;
		$this -> query_headers['Content-Length'] = strlen($content);
	}
	public function
	__toString(): string
	{
		return $this -> response;
	}
}

?>
