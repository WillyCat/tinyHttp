<?php

/*
Date        Ver  Change
----------  ---  -------------------------------------------------------
            0.1  Started (partial)
            0.2 
            0.3  splitted tinyHttp introducing tinyHttpReponse
            1.0  First working version
2018-10-09  1.1  new methods: tinyHttp::removeHeader(), tinyHttp::setDebug()
2018-10-19  1.1a added missing prototypes
2018-11-27  1.2  new methods: tinyHttp::getHeaders(), tinyHttp::getVersion()
                 added 'Host:' header (mandatory in HTTP 1.1)
                 moved url analysis from send() to setUrl()
                 setUrl can now throw an exception if url is not correct
                 tinyHttp::port set from url when provided
2018-12-17  1.3  - new methods: resetHeaders(), setHeader()
                 - when Content-Length header is not provided, getContentLength() returned null, now returns text length
*/


// minimal http class using only native php functions
// whenever possible, interface mimics pear http_request2
// this class is mainly a wrapper around file_get_contents() so it will behave just as file_get_contents does

// usage:
//
// try {
// $h = new tinyHttp ('http://www.site.com');
// $h -> getUrl() // http://www.site.com
// $h -> getProtocol() // string (http)
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
// Limits (far from exhaustive):
// - no chaining of methods
//

class tinyHttp_Exception extends Exception
{
}

class tinyHttp_LogicException extends tinyHttp_Exception
{
}

class tinyHttpQuery
{
}

class tinyHttpResponse
{
	private $code;
	private $headers;
	private $content;

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
		$this -> headers [$name] = $value;
	}

	public function
	setHeaders (array $headers): void
	{
		$this -> resetHeaders();
		foreach ($headers as $hdr)
		{
			$t = explode (':', $hdr, 2);

			if (count ($t) > 1)
				$this -> headers [trim($t[0])] = trim($t[1]);
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
			$l = strlen ($this -> content);
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
			"200"  =>  "OK",
			"201"  =>  "Created",
			"202"  =>  "Accepted",
			"203"  =>  "Non-Authoritative Information",
			"204"  =>  "No Content",
			"205"  =>  "Reset Content",
			"206"  =>  "Partial Content",
			"300"  =>  "Multiple Choices",
			"301"  =>  "Moved Permanently",
			"302"  =>  "Found",
			"303"  =>  "See Other",
			"304"  =>  "Not Modified",
			"305"  =>  "Use Proxy",
			"307"  =>  "Temporary Redirect",
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
			"416"  =>  "Requested range not satisfiable",
			"417"  =>  "Expectation Failed",
			"500"  =>  "Internal Server Error",
			"501"  =>  "Not Implemented",
			"502"  =>  "Bad Gateway",
			"503"  =>  "Service Unavailable",
			"504"  =>  "Gateway Time-out",
			"505"  =>  "HTTP Version not supported"
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
		return $this -> content;
	}
}

class tinyHttp
{
	const METHOD_GET  = 'GET';
	const METHOD_POST = 'POST';

	// parms
	private $method;	// METHOD_GET, METHOD_POST
	private $url;           // as provided
	private $protocol = ''; // 'http', 'https' computed from url
	private $host = '';	// computed from url
	private $port = 0;      // computed from url

	private $follow_redirects = false;
	private $max_redirects = 10;
	private $debugLevel;    // 0, 1

	// query
	private $query_headers; // array of name => value
	private $query_content; // string, as sent

	private $response;	// tinyHttpResponse object

	// protocol must be 'http' or 'https'
	public function
	__construct(string $url = '', $method = tinyHttp::METHOD_GET)
	{
		$this -> url = '';

		if ($url != '')
			$this -> setUrl ($url);

		$this -> setMethod ($method);
		$this -> resetHeaders();
		$this -> setContent ('');
	}

	public function
	getVersion(): string
	{
		return '1.2';
	}

	public function
	setDebug (int $debugLevel): void
	{
		$this -> debugLevel = $debugLevel;
		if ($this -> debugLevel > 0)
			echo 'Starting debug of ' .  __FILE__ . "\n";
	}

	//
	// URL
	//

	public function
	getProtocol(): string
	{
		return $this -> protocol;
	}

	public function
	getHost(): string
	{
		return $this -> host;
	}

	public function
	setPort (int $port): void
	{
		$this -> port = $port;
	}

	public function
	setUrl(string $url): void
	{
		// 1.2: moved here (was in send() before)

		if( !preg_match( "#([^:]+):#", $url, $out ) )
			throw new tinyHttp_Exception ('url should start with protocol: ');

		$url_parts = parse_url ($url);
		if (!$url_parts)
			throw new tinyHttp_Exception ('ill formed url');

		$this -> protocol = $url_parts['scheme'];
		if ($this -> debugLevel > 0)
			echo "protocol: " . $this -> protocol . "\n";
		$this -> host     = $url_parts['host'];
		if ($this -> debugLevel > 0)
			echo "host: " . $this -> host . "\n";

		// 1.2
		if (array_key_exists ('port', $url_parts))
			$this -> setPort ($url_parts['port']);

		$this -> url = $url;
	}

	public function
	getUrl(): string
	{
		return $this -> url;
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
		if ($this -> url == '')
			throw new tinyHttp_Exception ('no valid url provided');

		if (!in_array ($this -> method, [ 'GET', 'POST' ] ))
			throw new tinyHttp_Exception ('method not implemented: ' . $this -> method);

		if (!in_array ($this -> protocol, [ 'http', 'https' ] ))
			throw new tinyHttp_Exception ('protcol is not supported');

		$http_context = [ ];
		$http_context['method']           = $this -> method;
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

		// 1.2
		$host = $this -> host;
		if ($this -> port != 0)
			$host .= ':' . $this -> port;
		$this -> setHeader ('Host', $host);

		$header = '';
		foreach ($this -> query_headers as $name => $value)
			$header .= $name . ': ' . $value . "\r\n";
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

		if ($this -> debugLevel > 0)
			echo "building context..." . "\n";
		$context = stream_context_create([ 'http' => $http_context ]);
		if ($this -> debugLevel > 0)
			echo "context built" . "\n";

		if ($this -> debugLevel > 0)
			echo "creating tinyHttpResponse..." . "\n";
		$this -> response = new tinyHttpResponse();
		if ($this -> debugLevel > 0)
			echo "tinyHttpResponse created" . "\n";

		// if an error occurs, file_get_contents will not raise an exception
		// but display error message
		// we do not want the message to pollute display but we do want to miss the message
		// to have file_get_contents raise an exception with the error message,
		// we do the following :
		// 1. set an error handler that will catch any error occurring
		// 2. have the error handler raise an exception with the error message

		if ($this -> debugLevel > 0)
			echo "setting error handler..." . "\n";
		set_error_handler(
		    create_function(
			'$severity, $message, $file, $line',
			'throw new ErrorException($message, 0, $severity, $file, $line);'
		    )
		);
		if ($this -> debugLevel > 0)
			echo "error handler set" . "\n";

		try
		{
			if ($this -> debugLevel > 0)
			{
				echo "Sending request" . "\n";
				echo 'url: ' . $this -> url . "\n";
			}
			$content = file_get_contents ($this -> url, false, $context);
			if ($this -> debugLevel > 0)
				echo "Got a response" . "\n";
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
		if ($this -> debugLevel > 0)
			echo "setting method to " . $method . "\n";
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
		if ($this -> debugLevel > 0)
			echo "setting header:  " . $name . ": " . $value . "\n";
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
