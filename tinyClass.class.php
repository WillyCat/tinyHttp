<?php
/**
 * Date        Ver  Change
 * ----------  ---  -------------------------------------------------------
 * 2019-08-05  1.0  First version (was part of tinyHttp)
 */

// Improvement ideas :
// - chaining of methods
//
// Note:
// 'private' can be called by tinyClass only
// 'protected' can be called by tinyHttp object (heriting from tinyClass)
// 'public' can be called outside tinyHttp object (instanciating tinyHttp)
trait tinyDebug
{
	protected $debugLevel = 0;
	private $debugChannel = 'stdout'; // 'stdout', 'file'
	private $debugFile = null;
	private $debugFilename = '';
	/**
	 *
	 * @param string $message
	 * @param string $type I/W/E
	 */
	protected function
	debug (string $message, string $type = 'I', int $debugLevel = 0): void
	{
		if (!$this -> debugLevel) // no log required
		{
//echo 'msg is ['.$message.'], debugLevel is ' . $this -> debugLevel . "\n";
			return;
		}
		if ($debugLevel != 0)	// if level is set for this message
			if ($debugLevel < $this -> debugLevel) // but lower than threshold
				return; // then do not issue this message
		$cols = [ ];
		$cols[] = date ('Y-m-d H:i:s');
		$cols[] = sprintf('%-5d',getmypid());
		$cols[] = $type;
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
	/**
	 *
	 */
	private function
	closeCurrentChannel(): void
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
	/**
	 *
	 * @param string $channel 'stdout','file'
	 * @param string $opt meaning depends on the channel - For 'file', filename
	 * @throws Exception if channel value is incorrect
	 */
	private function
	openChannel (string $channel, string $opt): void
	{
		switch ($channel)
		{
		case 'stdout' :
			break;
		case 'file' :
			$this -> fp = fopen ($opt, 'a+');
			$this -> debugFilename = $opt;
			break;
		default :
			throw new Exception ('tinyDebug::openChannel: incorrect value for channel ');
		}
		$this -> debugChannel = $channel;
	}
	/**
	 *
	 * @param string $channel 'stdout','file'
	 * @param string $opt meaning depends on the channel - For 'file', filename
	 * @throws Exception if channel value is incorrect
	 */
	public function
	setDebugChannel (string $channel, string $opt = ''): void
	{
		$this -> closeCurrentChannel();
		$this -> openChannel($channel, $opt);
	}
	/**
	 *
	 * @return string returns current debug channel
	 */
	public function
	getDebugChannel (): string
	{
		return $this -> debugChannel;
	}
	/**
	 *
	 * @param string $debugLevel set a debug level - 0 means no log - higher means more
	 */
	public function
	setDebugLevel (int $debugLevel): void
	{
		$this -> debugLevel = $debugLevel;
		if ($this -> debugLevel > 0 && is_null ($this -> debugChannel))
			$this -> openChannel ('stdout');
	}
	/**
	 *
	 * @return int returns current debug level
	 */
	public function
	getDebugLevel (): int
	{
		return $this -> debugLevel;
	}
}

class tinyClass // main class for all "tiny" classes
{
	use tinyDebug;
}


?>
