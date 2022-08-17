<?php

//================================================================
/**
 * tinyUrl is a simple class to manage URL
 *
 * url management
 * This class is mainly a wrapper around parse_url()
 * plus a method to build an url from its parts
 *
 * Analyze an URL
 * $u = new tinyUrl ('http://www.example.com:8080/index.html?x=A&y=B');
 * $u -> getScheme() ==> 'http'
 * $u -> getHost()   ==> 'www.example.com'
 * $u -> getPort()   ==> 8080
 * $u -> getPath()   ==> '/index.html'
 * $u -> getQuery()  ==> 'x=A&y=B'
 * $u -> getOrigin() ==> 'http://www.example.com'
 *
 * Build an URL
 * $u = new tinyUrl ();
 * $u -> setScheme ('https');
 * $u -> setHost ('www.example.ccom');
 * $u -> setPort (8080);
 * $u -> setPath ('/index.html');
 * $u -> setQuery([ 'x' => 'A', 'y' => 'B' ])
 * $u -> getUrl() => 'https://www.example.com:8080/index.html?x=A&y=B'
 * $u -> getOrigin() => 'http://www.example.com'
 *
 * Mixed
 * $u = new tinyUrl ();
 * $u -> setUrl ('http://www.example.com/index.html');
 * $u -> getScheme() ==> 'http'
 * $u -> setUser ('john');
 * $u -> setPass ('secret');
 * $u -> setQuery([ 'x' => 'A', 'y' => 'B' ])
 * $u -> getUrl() => 'http://john:secret@www.example.com/index.html?x=A&y=B'
 * $u -> getOrigin() => 'http://www.example.com'
 * echo $u => http://john:secret@www.example.com/index.html?x=A&y=B
 *
 */
class tinyUrl extends tinyClass
{
	// full URL
	private $url = null;
	// parts of the URL
	private $scheme = '';
	private $host = '';
	private $port = 0;
	private $user = '';
	private $pass = '';
	private $path = '/';
	private $query = '';
	private $fragment = '';
	/**
	 *  Constructor
	 *
	 * @param string $url Optional - used to initialize with a partial or full url
	 */
	public function
	__construct (string $url = '')
	{
		$this -> setUrl($url);
	}
	public function
	setUrl (string $url): void
	{
		if ($url == '')
			return;
		if( !preg_match( "#([^:]+):#", $url, $out ) )
			throw new tinyHttp_Exception ('url should start with scheme: ');
		$url_parts = parse_url ($url);
		if (!$url_parts)
			throw new tinyHttp_Exception ('ill formed url');
		$this -> url = $url;
		//
		// Scheme
		// setScheme() will also set default port for this scheme
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
		if (!array_key_exists ('host', $url_parts))
			throw new Exception ('Missing host in url ('.$url.')');
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

		$this -> debug ( "URL set to: " . $this -> getUrl() );
	}
	//             origin
	//               |
	//     |-------- + ---------|
	//     v                    v
	//  ______              _________
	// /      \            /         \
	// https://john:secret@example.com:8042/over/there?name=ferret&way=up#nose
 	// \___/   \__/ \____/ \_________/ \__/\_________/ \________________/ \__/
	//   |       |     |        |        |      |              |            |  
	// scheme   user pass      host     port   path          query       fragment
        //         \__________________________/
        //                       |    
	//                   authority
	// \_____________________________________________________________________/
	//                                   |
	//                                  url
	//
	/**
	 * Get authority part of the url
	 * @return string
	 */
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

	/**
	 * Get origin (scheme + host)
	 *
	 * @return string
	 */
	public function
	getOrigin(): string
	{
		return $this -> getScheme() . '://' . $this -> getHost();
	}

	// -------------------------------------------------------
	// Fragment (=anchor) management
	// setFragment : set content
	// getFragment : get content
	/**
	 * @param string $fragment Set fragment to this value
	 * @return void
	 */
	public function
	setFragment(string $fragment): void
	{
		$this -> fragment = $fragment;
	}
	/**
	*  Get fragment of the url
	 * @return string
	 */
	public function
	getFragment(): string
	{
		return $this -> fragment;
	}
	// -------------------------------------------------------
	// Query management
	// setQuery : set content
	// resetQuery : clear content
	// addQuery : add an item to content
	// getQuery : get the result as a string useable in a URL
	/**
	 * @param mixed $q either a formatted string or an array of key/value
	 * @param int $enc_type possible values: PHP_QUERY_RFC1738, PHP_QUERY_RFC3986. With 1738, ' ' becomes '+', with 3986, ' ' becomes '%20'
	 * @return void
	 */
	public function
	setQuery($q, int $enc_type = PHP_QUERY_RFC3986): void
	{
		if (is_array ($q))
			$query = http_build_query ($q, $enc_type);
		else
			$query = $q;
		$this -> query = $query;
	}
	/**
	 * @param string $parm Parameter name
	 * @param string $value Parameter value
	 * @param int $enc_type Encoding type (PHP_QUERY_RFC1738, PHP_QUERY_RFC3986)
	 * @return void
	 */
	public function
	addQuery (string $parm, string $value, $enc_type = PHP_QUERY_RFC3986): void
	{
		if ($this -> query != '')
			$this -> query .= '&';
		$this -> query .= http_build_query ([ $parm => $value ], $enc_type);
	}
	/**
	 *
	 * @return void
	 */
	public function
	resetQuery(): void
	{
		$this -> setQuery('');
	}
	/**
	 *
	 * @return string
	 */
	public function
	getQuery(): string
	{
		return $this -> query;
	}
	// -------------------------------------------------------
	/**
	 * Set Path
	 *
	 * @param string $path New value for path
	 * @return void
	 */
	public function
	setPath(string $path): void
	{
		$this -> path = $path;
	}
	/**
	 * Get path
	 *
	 * @return string
	 */
	public function
	getPath(): string
	{
		return $this -> path;
	}
	// -------------------------------------------------------
	// Password management
	/**
	 * Set pass value
	 *
	 * @param string $pass New value
	 * @return void
	 */
	public function
	setPass(string $pass): void
	{
		$this -> pass = $pass;
	}
	/**
	 * Get pass value
	 *
	 * @return string
	 */
	public function
	getPass(): string
	{
		return $this -> pass;
	}
	// -------------------------------------------------------
	// User management
	/**
	 * Set user value
	 *
	 * @param string $user New value
	 */
	public function
	setUser(string $user): void
	{
		$this -> user = $user;
	}
	/**
	 * Get user value
	 *
	 * @return string
	 */
	public function
	getUser(): string
	{
		return $this -> user;
	}
	// -------------------------------------------------------
	// Scheme
	/**
	 * Get scheme value
	 *
	 * @return string
	 */
	public function
	getScheme(): string
	{
		return $this -> scheme;
	}
	/**
	 * Test is scheme is valid
	 *
	 * @param string $scheme
	 * @return bool
	 */
	private function
	isValidScheme (string $scheme): bool
	{
		return (in_array ($scheme, [ 'http', 'https' ] ));
	}
	/**
	 * Throws an exception is scheme cannot be used
	 *
	 * A scheme can be used if it is valid and current environment
	 * is able to handle it.
	 *
	 * @param string $scheme
	 * @return void
	 * @throws tinyHttp_Exception
	 */
	private function
	validateScheme (string $scheme): void
	{
		if (!self::isValidScheme ($scheme))
			throw new tinyHttp_Exception ('scheme is not supported');
		if ($scheme == 'https')
			if (!extension_loaded ('openssl'))
				throw new tinyHttp_Exception ('https requires openssl extension');
	}
	/**
	 * Set scheme
	 *
	 * @param string $scheme
	 * @return void
	 */
	public function
	setScheme(string $scheme): void
	{
		self::validateScheme ($scheme);
		$this -> debug ( "scheme: " . $scheme );
		$this -> scheme = $scheme;
		if ($this -> port == 0)
			$this -> setPort(self::getDefaultPort($scheme));
	}
	// -------------------------------------------------------
	// Host management
	/**
	 * Get host
	 *
	 * @return string
	 */
	public function
	getHost(): string
	{
		return $this -> host;
	}
	public function
	setHost (string $host): void
	{
		$this -> host = $host;
	}
	// -------------------------------------------------------
	// Port management
	/**
	 * Returns default port for supported shemes
	 *
	 * @param string $scheme
	 * @return int Default port for this scheme
	 * @throws Exception when scheme is not supported
	 */
	private function
	getDefaultPort (string $scheme): int
	{
		switch ($scheme)
		{
		case 'http'  : return 80;
		case 'https' : return 443;
		default      : throw new Exception ('Unknown scheme: ' . $scheme);
		}
	}
	/**
	 * Set port
	 *
	 * @param int $port
	 * @return void
	 */
	public function
	setPort (int $port): void
	{
		$this -> port = $port;
	}
	/**
	 * Get port
	 *
	 * @return int
	 */
	public function
	getPort (): int
	{
		return $this -> port;
	}
	/**
	 * Determine is current port is the standard one for current scheme
	 *
	 * @return bool
	 */
	public function
	isStandardPort (): bool
	{
		return $this -> getPort() == $this -> getDefaultPort($this -> scheme);
	}
	// -------------------------------------------------------
	// Magic functions
	/**
	 * __toString magic function
	 *
	 * @return string
	 */
	public function
	__toString(): string
	{
		return $this -> getUrl();
	}
	// -------------------------------------------------------
	// URL management
	/**
	 * Get url
	 *
	 * @return string
	 */
	public function
	getUrl(): string
	{
		$parts = [];
		$parts[] = $this -> scheme . '://';
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

?>
