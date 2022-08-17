<?php

require_once 'tinyClass.class.php';

//================================================================
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
	setContent (string $content): void
	{
		$this -> content = $content;
	}
	public function
	appendContent(string $content): void
	{
		if ($this -> content == null)
			$this -> content = '';
		$this -> content .= $content;
	}
	public function
	resetContent(): void
	{
		$this -> content = null;
	}
	public function
	resetHeaders (): void
	{
		$this -> headers = [ ];
	}
	private function
	normalize (string $name): string
	{
		$name = trim($name);
		$name = strtolower ($name);
		return $name;
	}
	public function
	addHeader (string $header_name, string $value): void
	{
		$name = $this -> normalize ($header_name);
		if (array_key_exists ($name, $this -> headers))
			if (is_array ($this -> headers [$name]))
				$this -> headers [$name][] = $value;
			else
			{
				$value1 = $this -> headers [$name];
				$this -> headers [$name] = [ ];
				$this -> headers [$name][] = $value1;
				$this -> headers [$name][] = $value;
			}
		else
			$this -> headers [$name] = trim($value);
	}
	/**
	* header is an array of strings
	* format is either :
	* HTTP/1.1 301 Moved Permanently
	* or
	* Header-Name: header value
	* some headers can be sent multiple times (like Set-Cookie)
	*/
	public function
	setHeaders (array $headers): void
	{
		$this -> resetHeaders();
		foreach ($headers as $hdr)
		{
			$t = explode (':', $hdr, 2);
			if (count ($t) > 1) 
				$this -> addHeader ($t[0], $t[1] );
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
	getCookie(): string
	{
		return $this -> getHeader('cookie');
	}
	public function
	getHeaders(): array
	{
		return $this -> headers;
	}
	/**
	* if no such header, returns null
	* if header appears multiple times, returns an array of values
	* if header appears one, depends on returnSingleAsString
	*    if true, returns a string
	*    if false, return a singleton
	*/
	public function
	getHeader (string $header_name, bool $returnSingleAsString = true)
	{
		$header_name = $this -> normalize($header_name);
		if (!array_key_exists ($header_name, $this -> headers))
			return null;

		if (is_array ($this -> headers[$header_name]))
			return $this -> headers[$header_name];

		if ($returnSingleAsString)
			return $this -> headers[$header_name];
		else
			return array ($this -> headers[$header_name]);
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

?>
