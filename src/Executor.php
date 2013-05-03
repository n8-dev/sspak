<?php

/*
 * Responsible for executing commands.
 *
 * This could probably be replaced with something from Symfony, but right now this simple implementation works.
 */
class Executor {
	protected $defaultOptions = array(
		'throwException' => true,
		'inputContent' => null,
		'inputFile' => null,
		'outputFile' => null,
		'outputFileAppend' => false,
	);

	/**
	 * @param string $command The command
	 * @param boolean $throwException If true, an Exception will be thrown on a nonzero error code
	 * @param boolean $returnOutput If true, output will be captured
	 * @param boolean $inputContent Content for STDIN. Otherwise the parent script's STDIN is used
	 * @return A map containing 'return', 'output', and 'error'
	 */
	function execLocal($command, $options = array()) {
		$options = array_merge($this->defaultOptions, $options);

		if(is_array($command)) $command = $this->commandArrayToString($command);

		$pipes = array();
		$pipeSpec = array(
			0 => STDIN,
			1 => array('pipe', 'w'),
			2 => STDERR,
		);

		// Alternatives
		if($options['inputContent']) $pipeSpec[0] = array('pipe', 'r');
		if($options['outputFile']) $pipeSpec[1] = array('file',
				$options['outputFile'], 
				$options['outputFileAppend'] ? 'a' : 'w');

		$process = proc_open($command, $pipeSpec, $pipes);

		if($options['inputContent']) fwrite($pipes[0], $options['inputContent']);
	
		$result = array();

		if(isset($pipes[1])) {
			$result['output'] = stream_get_contents($pipes[1]);
			fclose($pipes[1]);
		}
		if(isset($pipes[2])) {
			$result['error'] = stream_get_contents($pipes[2]);
			fclose($pipes[2]);
		}

		$result['return'] = proc_close($process);

		if($options['throwException'] && $result['return'] != 0)	{
			throw new Exception("Command: $command\nExecution failed: returned {$result['return']}.\n"
				. (empty($result['output']) ? "" : "Output:\n{$result['output']}"));
		}

		return $result;
	}

	function execRemote($server, $command, $options = array()) {
		if(is_array($command)) $command = $this->commandArrayToString($command);

		if(!empty($options['outputFile'])) return $this->execLocal(array("ssh", $server, $command), $options);
		else return $this->execLocal(array("ssh", "-t", $server, $command), $options);
	}

	/**
	 * Turn an array command in a string, escaping and concatenating each item
	 * @param array $command Command array. First element is the command and all remaining are the arguments.
	 * @return string String command
	 */
	function commandArrayToString($command) {
		$string = escapeshellcmd(array_shift($command));
		foreach($command as $arg) {
			$string .= ' ' . escapeshellarg($arg);
		}
		return $string;
	}

}