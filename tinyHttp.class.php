<?php

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
// - the only supported method is GET
// - no chaining of methods
//

/*
0.1 First
0.2 
0.3 splitted tinyHttp introducing tinyHttpReponse
*/

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
	setContent ($content)
	{
		$this -> content = $content;
	}

	public function
	setHeaders ($headers)
	{
		$this -> headers = [ ];
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
			$l = 0;
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
	private $url;
	private $method;	// METHOD_...
	private $protocol;
	private $host;
	private $port;
	private $follow_redirects = false;
	private $max_redirects = 10;

	// query
	private $query_headers;
	private $query_content;

	private $response;

	// protocol must be 'http' or 'https'
	public function
	__construct(string $url = '', $method = tinyHttp::METHOD_GET)
	{
		$this -> setUrl ($url);
		$this -> setMethod ($method);
		$this -> resetHeaders();
		$this -> setContent ('');
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
	setUrl(string $url): void
	{
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

	// returns body
	public function
	send()
	{
		if( !preg_match( "#([^:]+):#", $this -> url, $out ) )
			throw new tinyHttp_Exception ('url should start with protocol: ');

		if (!in_array ($this -> method, [ 'GET', 'POST' ] ))
			throw new tinyHttp_Exception ('method not implemented: ' . $this -> method);

		$url_parts = parse_url ($this -> url);
		if (!$url_parts)
			throw new tinyHttp_Exception ('ill formed url');

		$this -> protocol = $url_parts['scheme'];
		$this -> host     = $url_parts['host'];

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
		// $http_context['content'] = $data	// when POST - made with http_build_query()

		/*
		Note that if you set the protocol_version option to 1.1 and the server you are requesting from is configured to use keep-alive connections, the function (fopen, file_get_contents, etc.) will "be slow" and take a long time to complete. This is a feature of the HTTP 1.1 protocol you are unlikely to use with stream contexts in PHP.
		Simply add a "Conection: close" header to the request to eliminate the keep-alive timeout:
		*/
		$this -> query_headers['Connection'] = 'Close';

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

		$context = stream_context_create([ 'http' => $http_context ]);

		$this -> response = new tinyHttpResponse();

		// if an error occurs, file_get_contents will not raise an exception
		// but display error message
		// we do not want the message to pollute display but we do want to miss the message
		// to have file_get_contents raise an exception with the error message,
		// we do the following :
		// 1. set an error handler that will receive the error message
		// 2. have the error handler raise an exception with the error message

		set_error_handler(
		    create_function(
			'$severity, $message, $file, $line',
			'throw new ErrorException($message, $severity, $severity, $file, $line);'
		    )
		);

		try
		{
			$content = @file_get_contents ($this -> url, false, $context);
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
		$this -> query_headers[$name] = $value;
	}

	// shorthand for Content-Type header
	public function
	setContentType (string $contentType): void
	{
		$this -> setHeader('Content-Type', $contentType);
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
