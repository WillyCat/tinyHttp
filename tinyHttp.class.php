<?php

/*
Date        Ver  Change
----------  ---  -------------------------------------------------------
            0.1  Started (partial)
            0.2 
            0.3  splitted tinyHttp introducing tinyHttpReponse
            1.0  First working version
2018-10-09  1.1  new methods: tinyHttp::removeHeader(), tinyHttp::setDebugLevel()
2018-10-19  1.1a added missing prototypes
2018-11-27  1.2  new methods: tinyHttp::getHeaders(), tinyHttp::getVersion()
                 added 'Host:' header (mandatory in HTTP 1.1)
                 moved url analysis from send() to setUrl()
                 setUrl can now throw an exception if url is not correct
                 tinyHttp::port set from url when provided
2018-12-17  1.3  - new methods: resetHeaders(), setHeader()
                 - when Content-Length header is not provided, getContentLength() returned null, now returns text length
2018-12-26  1.4                 
                 - bugfix: getVersion() was still returning 1.2 (fixed)
                 - when using https, checks that openssl is loaded
                 - new class tinyUrl
                 - new trait tinyDebug, improved debug
                 - more http codes
                 - renamed "protocol" as "scheme"
*/

// minimal http class using only native php functions
// whenever possible, interface mimics pear http_request2
// this class is mainly a wrapper around file_get_contents() so it will behave just as file_get_contents does

// usage:
//
// try {
// $h = new tinyHttp ('http://www.site.com');
// $h -> getUrl() // http://www.site.com
// $h -> getScheme() // string (http)
// $h -> getHost() // string (www.site.com)
// $h -> getMethod() // string (get)
// $h -> setConfig ('follow_redirects', true);
// $h -> setConfig ('max_redirects', 10);
// $h -> setHeader ('Accept', 'application/vnd.discogs.v2.html+json');
// $r = $h -> send();
// $r -> getStatus() // 200
// $r -> getHeaders() // array of code:value
// $r -> getHeader('Content-Length') // header value or null
// $r -> getContentLength() // Content-Length value or 0
// $r -> getBody() // string, can be empty
// } catch (tinyHttp_Exception $e) {
//   $e -> getMessage()
// }

// $h = new tinyHttp();
// $h -> setUrl('http://www.site.com');
//
// $h = new tinyHttp ('http://www.site.com', tinyHttp::METHOD_GET);
//
// class implements __toString() :
// echo new tinyHttp('http://www.site.com');
// will display the content of the page behind the url
//
// $h = new tinyHttp();
// $h -> setMethod('POST');
// $h -> setPostValues ([ 'q' => 'xxx', 'login' => $login ]);
// $h -> send();
//
// Improvement ideas :
// - chaining of methods
//

// 'private' can be called by tinyClass only
// 'protected' can be called by tinyHttp object (heriting from tinyClass)
// 'public' can be called outside tinyHttp object (instanciating tinyHttp)

trait tinyDebug
{
	private $debugLevel = 0;
	private $debugChannel = 'stdout'; // 'stdout', 'file'
	private $debugFile = null;
	private $debugFilename = '';

	protected function
	debug (string $message, string $level = 'I'): void
	{
		if (!$this -> debugLevel)
			return;

		$cols = [ ];
		$cols[] = date ('Y-m-d H:i:s');
		$cols[] = sprintf('%-5d',getmypid());
		$cols[] = $level;
		$cols[] = $message;

		$str = implode (' ', $cols);

		switch ($this -> debugChannel)
		{
		case 'stdout' :
			echo $str . "\n";
			break;
		case 'file' :
			fwrite ($this -> debugFile, $str . "\n");
			break;
		}
	}

	private function
	closeCurrentChannel()
	{
		switch ($this -> debugChannel)
		{
		case 'stdout' :
			break;

		case 'file' :
			fclose ($this -> debugFile);
			$this -> debugFile = null;
			break;
		}
	}

	private function
	openChannel (string $channel, string $opt)
	{
		switch ($channel)
		{
		case 'stdout' :
			break;

		case 'file' :
			$this -> fp = fopen ($opt, 'a+');
			$this -> debugFilename = $opt;
			break;
		}
		$this -> debugChannel = $channel;
	}

	public function
	setDebugChannel (string $channel, string $opt = ''): void
	{
		$this -> closeCurrentChannel();
		$this -> openChannel($channel, $opt);
	}

	protected function
	getDebugChannel (): string
	{
		return $this -> channel;
	}

	public function
	setDebugLevel (int $debugLevel): void
	{
		$this -> debugLevel = $debugLevel;
		if ($this -> debugLevel > 0 && is_null ($this -> debugChannel))
			$this -> openChannel ('stdout');
	}

	protected function
	getDebugLevel (): int
	{
		return $this -> debugLevel;
	}
}

class tinyClass // main class for all "tiny" classes
{
	use tinyDebug;
}

// url management
// This class is mainly a wrapper around parse_url()
// plus a method to build an url from its parts
//
// Analyze an URL
// $u = new tinyUrl ('http://www.example.com:8080/index.html?x=A&y=B');
// $u -> getScheme() ==> 'http'
// $u -> getHost()     ==> 'www.example.com'
// $u -> getPort()     ==> 8080
// $u -> getPath()     ==> '/index.html'
// $u -> getQuery()    ==> 'x=A&y=B'
//
// Build an URL
// $u = new tinyUrl ();
// $u -> setScheme ('https');
// $u -> setHost ('www.example.ccom');
// $u -> setPort (8080);
// $u -> setPath ('/index.html');
// $u -> setQuery([ 'x' => 'A', 'y' => 'B' ])
// $u -> getUrl() => 'https://www.example.com:8080/index.html?x=A&y=B'
//
// Mixed
// $u = new tinyUrl ();
// $u -> setUrl ('http://www.example.com/index.html');
// $u -> getScheme() ==> 'http'
// $u -> setUser ('john');
// $u -> setPass ('secret');
// $u -> setQuery([ 'x' => 'A', 'y' => 'B' ])
// $u -> getUrl() => 'http://john:secret@www.example.com/index.html?x=A&y=B'
// echo $u => => http://john:secret@www.example.com/index.html?x=A&y=B
//
class tinyUrl extends tinyClass
{
	// full URL
	private $url = null;

	// parts of the URL
	private $protocol = '';
	private $host = '';
	private $port = 0;
	private $user = '';
	private $pass = '';
	private $path = '/';
	private $query = '';
	private $fragment = '';

	public function
	__construct (string $url = '')
	{
		if ($url == '')
			return;

		if( !preg_match( "#([^:]+):#", $url, $out ) )
			throw new tinyHttp_Exception ('url should start with protocol: ');

		$url_parts = parse_url ($url);
		if (!$url_parts)
			throw new tinyHttp_Exception ('ill formed url');

		$this -> url = $url;

		//
		// Scheme
		// setScheme() will also set default port for this protocol
		//
		// http://john:secret@www.abc.com:8080/index.html?x=A&y=B#here
		// ^^^^
		//

		$this -> setScheme( $url_parts['scheme'] );

		//
		// User & Password
		//
		// http://john:secret@www.abc.com:8080/index.html?x=A&y=B#here
		//        ^^^^ ^^^^^^

		if (array_key_exists ('user', $url_parts))
			$this -> setUser ($url_parts['user']);

		if (array_key_exists ('pass', $url_parts))
			$this -> setPass ($url_parts['pass']);


		//
		// Host
		//
		// http://john:secret@www.abc.com:8080/index.html?x=A&y=B#here
		//                    ^^^^^^^^^^^
		//

		$this -> host     = $url_parts['host'];
		$this -> debug ( "host: " . $this -> host );

		//
		// Port
		//
		// http://john:secret@www.abc.com:8080/index.html?x=A&y=B#here
		//                                ^^^^
		//

		if (array_key_exists ('port', $url_parts))
			$this -> setPort ($url_parts['port']);
		//
		// Path
		//
		// http://john:secret@www.abc.com:8080/index.html?x=A&y=B#here
		//                                    ^^^^^^^^^^^

		if (array_key_exists ('path', $url_parts))
			$this -> setPath ($url_parts['path']);

		//
		// Query
		//
		// http://john:secret@www.abc.com:8080/index.html?x=A&y=B#here
		//                                                ^^^^^^^

		if (array_key_exists ('query', $url_parts))
			$this -> setQuery ($url_parts['query']);

		//
		// Anchor / Fragment
		//
		// http://john:secret@www.abc.com:8080/index.html?x=A&y=B#here
		//                                                        ^^^^

		if (array_key_exists ('fragment', $url_parts))
			$this -> setFragment ($url_parts['fragment']);
	}

	//
	// foo://john:secret@example.com:8042/over/there?name=ferret#nose
        // \_/   \__________________________/\_________/ \_________/ \__/
        //  |                  |                  |           |        |
	// scheme          authority             path       query   fragment
	//

	public function
	getAuthority(): string
	{
		$parts = [ ];
		if ($this -> user != '')
		{
			$parts[] = $this -> user;
			if ($this -> pass != '')
			{
				$parts[] = ':';
				$parts[] = $pass;
			}
			$parts[] = '@';
		}
		$parts[] = $this -> host;
		if ($this -> port != 0 && !$this -> isStandardPort() )
			$parts[] = ':' . $this -> port;

		return implode ('', $parts);
	}

	public function
	setFragment(string $fragment): void
	{
		$this -> fragment = $fragment;
	}

	public function
	getFragment(): string
	{
		return $this -> fragment;
	}

	public function
	setQuery(string $q, $enc_type = PHP_QUERY_RFC3986): void
	{
		if (is_array ($q))
			$query = http_build_query ($q, $enc_type);
		else
			$query = $q;

		$this -> query = $query;
	}

	public function
	getQuery(): string
	{
		return $this -> query;
	}

	public function
	setPath(string $path): void
	{
		$this -> path = $path;
	}

	public function
	getPath(): string
	{
		return $this -> path;
	}

	public function
	setPass(string $pass): void
	{
		$this -> pass = $pass;
	}

	public function
	getPass(): string
	{
		return $this -> pass;
	}

	public function
	setUser(string $user): void
	{
		$this -> user = $user;
	}

	public function
	getUser(): string
	{
		return $this -> user;
	}

	//
	// Scheme
	//

	public function
	getScheme(): string
	{
		return $this -> protocol;
	}

	private function
	isValidScheme (string $protocol): bool
	{
		return (in_array ($protocol, [ 'http', 'https' ] ));
	}

	private function
	validateScheme ($protocol): void
	{
		if (!self::isValidScheme ($protocol))
			throw new tinyHttp_Exception ('protocol is not supported');
		if ($protocol == 'https')
			if (!extension_loaded ('openssl'))
				throw new tinyHttp_Exception ('https requires openssl extension');
	}

	public function
	setScheme(string $protocol): void
	{
		self::validateScheme ($protocol);

		$this -> debug ( "protocol: " . $protocol );

		$this -> protocol = $protocol;

		if ($this -> port == 0)
			$this -> setPort(self::getDefaultPort($protocol));
	}

	//
	// Host
	//

	public function
	getHost(): string
	{
		return $this -> host;
	}

	//
	// Port
	//

	private function
	getDefaultPort (string $protocol): int
	{
		switch ($protocol)
		{
		case 'http'  : return 80;
		case 'https' : return 443;
		default      : throw new Exception ('Unknown protocol: ' . $protocol);
		}
	}

	public function
	setPort (int $port): void
	{
		$this -> port = $port;
	}

	public function
	getPort (): int
	{
		return $this -> port;
	}

	public function
	isStandardPort (): bool
	{
		return $this -> getPort() == $this -> getDefaultPort($this -> protocol);
	}

	public function
	__toString(): string
	{
		return $this -> getUrl();
	}

	public function
	getUrl(): string
	{
		$parts = [];
		$parts[] = $this -> protocol . '://';
		$parts[] = $this -> getAuthority();
		$parts[] = $this -> path;
		if ($this -> query != '')
			$parts[] = '?' . $this -> query;
		if ($this -> fragment != '')
			$parts[] = '#' . $this -> fragment;

		$this -> url = implode ('', $parts);

		return $this -> url;
	}
}

class tinyHttp_Exception extends Exception
{
}

class tinyHttp_LogicException extends tinyHttp_Exception
{
}

class tinyHttpQuery
{
}

// reponse to an http request

class tinyHttpResponse extends tinyClass
{
	private $code;
	private $headers;
	private $content = null;
	private $user_agent;

	public function
	__construct()
	{
	}

	public function
	setContent ($content): void
	{
		$this -> content = $content;
	}

	public function
	resetHeaders (): void
	{
		$this -> headers = [ ];
	}

	public function
	setHeader (string $name, string $value): void
	{
		$this -> headers [trim($name)] = trim($value);
	}

	public function
	setHeaders (array $headers): void
	{
		$this -> resetHeaders();
		foreach ($headers as $hdr)
		{
			$t = explode (':', $hdr, 2);

			if (count ($t) > 1)
				$this -> setHeader ($t[0], $t[1] );
			else
				if (preg_match('#HTTP/[0-9\.]+\s+([0-9]+)#',$hdr, $out ))
				{
					$this -> code = intval($out[1]);
				}
		}
	}

	public function
	getStatus(): int
	{
		return $this -> code;
	}

	public function
	getHeaders(): array
	{
		return $this -> headers;
	}

	public function
	getHeader ($header_name): ?string
	{
		if (array_key_exists ($header_name, $this -> headers))
			return $this -> headers[$header_name];
		else
			return null;
	}

	public function
	getContentLength(): int
	{
		$l = $this -> getHeader ('Content-Length');
		if (is_null ($l))
			if (is_null ($this -> content))	// no content
				$l = 0;
			else
				$l = strlen ($this -> content); // content exists, can be empty (0 byte long)
		return $l;
	}

	public function
	getBody(): string
	{
		return $this -> content;
	}

	public function
	getReasonPhrase(): string
	{
		// codes copied from www.w3.org
		$reasons = [
			"100"  =>  "Continue",
			"101"  =>  "Switching Protocols",
			"102"  =>  "Processing",
			"103"  =>  "Early Hints",
			"200"  =>  "OK",
			"201"  =>  "Created",
			"202"  =>  "Accepted",
			"203"  =>  "Non-Authoritative Information",
			"204"  =>  "No Content",
			"205"  =>  "Reset Content",
			"206"  =>  "Partial Content",
			"207"  =>  "Multi-Status",
			"208"  =>  "Already Reported",
			"226"  =>  "IM Used",
			"300"  =>  "Multiple Choices",
			"301"  =>  "Moved Permanently",
			"302"  =>  "Found", // was "Moved Temporarily"
			"303"  =>  "See Other",
			"304"  =>  "Not Modified",
			"305"  =>  "Use Proxy",
			"305"  =>  "Switch Proxy",
			"307"  =>  "Temporary Redirect",
			"307"  =>  "Permanent Redirect",
			"400"  =>  "Bad Request",
			"401"  =>  "Unauthorized",
			"402"  =>  "Payment Required",
			"403"  =>  "Forbidden",
			"404"  =>  "Not Found",
			"405"  =>  "Method Not Allowed",
			"406"  =>  "Not Acceptable",
			"407"  =>  "Proxy Authentication Required",
			"408"  =>  "Request Time-out",
			"409"  =>  "Conflict",
			"410"  =>  "Gone",
			"411"  =>  "Length Required",
			"412"  =>  "Precondition Failed",
			"413"  =>  "Request Entity Too Large",
			"414"  =>  "Request-URI Too Large",
			"415"  =>  "Unsupported Media Type",
			"416"  =>  "Range Not satisfiable",
			"417"  =>  "Expectation Failed",
			"418"  =>  "I'm a teapot",
			"419"  =>  "Page expired (unofficial)",
			"420"  =>  "Method Failure (unofficial)",
			"421"  =>  "Misdirected Request",
			"422"  =>  "Unprocessable Entity",
			"423"  =>  "Locked",
			"424"  =>  "Failed Dependency",
			"426"  =>  "Upgrade Required",
			"428"  =>  "Precondition Required",
			"429"  =>  "Too Many Requests",
			"431"  =>  "Request Header Fields Too Large",
			"440"  =>  "Login Time-out (IIS)",
			"450"  =>  "Blocked by Windows Parental Controls (unofficial)",
			"451"  =>  "Unavailable for Legal Reasons",
			"500"  =>  "Internal Server Error",
			"501"  =>  "Not Implemented",
			"502"  =>  "Bad Gateway",
			"503"  =>  "Service Unavailable",
			"504"  =>  "Gateway Time-out",
			"505"  =>  "HTTP Version not supported",
			"506"  =>  "Variant Also Negociates",
			"507"  =>  "Insufficient Storage",
			"508"  =>  "Loop Detected",
			"509"  =>  "Bandwidth Limit Exceeded (unofficial)",
			"510"  =>  "Not Extended",
			"511"  =>  "Network Authentication Required",
			"520"  =>  "Unknown Error (Cloudflare)",
			"521"  =>  "Webserver is down (Cloudflare)",
			"522"  =>  "Connection Timed Out down (Cloudflare)",
			"523"  =>  "Origin is Unreachable (Cloudflare)",
			"524"  =>  "A Timeout Occured (Cloudflare)",
			"525"  =>  "SSL Handshake Failed (Cloudflare)",
			"526"  =>  "Invalid SSL Certificate (unofficial)",
			"527"  =>  "Railgun Error (Cloudflare)",
			"530"  =>  "Origin DNS Error (Cloudflare)"
			];

		$status = $this -> getStatus();
		if (array_key_exists ($status, $reasons))
			return $reasons[$status];
		else
			return '';
	}


	public function
	__toString(): string
	{
		if (is_null ($this -> content))	// no content
			return '';

		// content exists (can be empty)

		return $this -> content;
	}
}

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

	// protocol must be 'http' or 'https'
	public function
	__construct(string $url = '', $method = tinyHttp::METHOD_GET)
	{
		$this -> url = null;
		if ($url != '')
			$this -> setUrl ($url);

		$this -> setMethod ($method);
		$this -> resetHeaders();
		$this -> setContent ('');
	}

	public function
	setUrl (string $url): void
	{
		$this -> url = new tinyUrl ($url);
	}

	public function
	getVersion(): string
	{
		return '1.4';
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
	setConfigItem ($name, $value)
	{
		switch ($name)
		{
		case 'follow_redirects' : $this -> follow_redirects = $value; break;
		case 'max_redirects' : $this -> max_redirects = $value; break;
		default : throw new tinyHttp_LogicException('unknown parameter: '.$name);
		}
	}

	//
	// Run !
	//

	public function
	send(): tinyHttpResponse
	{
		if (is_null ($this -> url))
			throw new tinyHttp_Exception ('no valid url provided');

		if (!in_array ($this -> method, [ 'GET', 'POST' ] ))
			throw new tinyHttp_Exception ('method not implemented: ' . $this -> method);

		$http_context = [ ];
		$http_context['method'] = $this -> method;

		// we directly set User-Agent header
		// $http_context['user_agent']

		// $http_context['proxy']
		// $http_context['request_fulluri']
		$http_context['protocol_version'] = 1.1;
		// $http_context['timeout']
		$http_context['follow_location']  = $this -> follow_redirects;
		$http_context['max_redirects']    = $this -> max_redirects;
		$http_context['ignore_errors'] = true; // if false, file_get_contents() will return false if 404 - if true, will return data

		/*
		Note that if you set the protocol_version option to 1.1 and the server you are requesting from is configured to use keep-alive connections, the function (fopen, file_get_contents, etc.) will "be slow" and take a long time to complete. This is a feature of the HTTP 1.1 protocol you are unlikely to use with stream contexts in PHP.
		Simply add a "Conection: close" header to the request to eliminate the keep-alive timeout:
		*/

		// $this -> setHeader ('Connection', 'Close');

		$host = $this -> url -> getHost();
		if (!$this -> url -> isStandardPort()) // port can be omitted if standard
			$host .= ':' . $this -> url -> getPort();
		$this -> setHeader ('Host', $host);

		$header = '';
		$hdr = 1;
		foreach ($this -> query_headers as $name => $value)
		{
			$header .= $name . ': ' . $value . "\r\n";
			$this -> debug ('Header #' . $hdr++ . ': ' . $name . ': ' . $value );
		}
		$http_context['header'] = $header;

/*
Accept-Encoding: gzip, deflate, br
Accept-Language: fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3
Cache-Control: max-age=0
Connection: keep-alive
Cookie: _ga=GA1.2.2135724536.151187987…Mb-M-XxntU_m56KegEWdM9qZVshXA
DNT: 1
Upgrade-Insecure-Requests	1
User-Agent: Mozilla/5.0 (Macintosh; Intel …) Gecko/20100101 Firefox/60.0
*/

		$http_context['content'] = $this -> query_content;

		$this -> debug ( "building context..." );

		// the entry is 'http' for http & https
		$context = stream_context_create([ 'http' => $http_context ]);
		$this -> debug ( "context built" );

		$this -> debug ( "creating tinyHttpResponse..." );
		$this -> response = new tinyHttpResponse();
		$this -> debug ( "tinyHttpResponse created" );
		$this -> response -> setDebugLevel ($this -> getDebugLevel() );

		// if an error occurs, file_get_contents will not raise an exception
		// but display error message
		// we do not want the message to pollute display but we do want to miss the message
		// to have file_get_contents raise an exception with the error message,
		// we do the following :
		// 1. set an error handler that will catch any error occurring
		// 2. have the error handler raise an exception with the error message

		$this -> debug ( "setting error handler..." );
		set_error_handler(
		    create_function(
			'$severity, $message, $file, $line',
			'throw new ErrorException($message, 0, $severity, $file, $line);'
		    )
		);
		$this -> debug ( "error handler set" );

		try
		{
			$this -> debug ( "Sending request" );
			$this -> debug ( 'url: ' . $this -> url -> getUrl());
			$reqStart = microtime(true);
			$content = file_get_contents ($this -> url -> getUrl(), false, $context);
			$reqEnd = microtime(true);
			$this -> debug ( "Got a response (took " .sprintf ('%.3f',($reqEnd - $reqStart)). " s)" );
			// $this -> debug ( $content );
			restore_error_handler();
		} catch (Exception $e) {
			restore_error_handler();
			throw new tinyHttp_Exception ($e -> getMessage());
		}


		if ($content === false)
			throw new tinyHttp_Exception ('failure');

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
		$this -> debug ( "setting method to " . $method );
		switch ($method)
		{
		case tinyHttp::METHOD_GET :
			break;
		case tinyHttp::METHOD_POST :
			$this -> setContentType ('application/x-www-form-urlencoded');
			break;
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
		if (array_key_exists ($name, $this -> query_headers))
			unset ($this -> query_headers[$name]);
	}

	public function
	getHeaders(): array
	{
		return $this -> query_headers;
	}

	// to set a single header:
	// setHeader ('header', 'value')
	// to set a group of headers:
	// setHeader ([ 'header1' => 'value1', 'header2', 'value2' ]);
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
		$this -> debug ( "setting header:  " . $name . ": " . $value );
		$this -> query_headers[$name] = $value;
	}

	// shorthand for Content-Type header
	public function
	setContentType (string $contentType): void
	{
		$this -> setHeader('Content-type', $contentType);
	}

	// shorthand for User-Agent header
	public function
	setUserAgent (string $userAgent): void
	{
		$this -> setHeader('User-Agent', $userAgent);
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
