<?php
/**
 * LVPEchoHandler module for Nuwani v2
 *
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPCommand {

	/**
	 * These constants define the different types of output a command can
	 * give. To trigger one of these, use the return statement somewhere in
	 * your command code. Note however that any output must be done using
	 * echo, print or any other command that will write to stdout.
	 *
	 * @var integer
	 */
	const OUTPUT_NORMAL  = 0;
	const OUTPUT_ERROR   = 1;
	const OUTPUT_INFO    = 2;
	const OUTPUT_NOTICE  = 3;
	const OUTPUT_SUCCESS = 4;
	const OUTPUT_USAGE   = 5;

	/**
	 * These constants define the various output modes we have available for
	 * the commands. The IRC mode is the default, outputting everything nice
	 * and easy to IRC without stripping colors or unneeded characters.
	 * However, the ingame modes will strip the colors and any prefixes that
	 * are not really needed, to save precious space. They will also tell us
	 * in what channel the output should be sent to and well as with that
	 * kind of prefix.
	 *
	 * @var integer
	 */
	const MODE_IRC       = 1;
	const MODE_CREW_CHAT = 2;
	const MODE_MAIN_CHAT = 3;

	/**
	 * The first word of a user's message this LVPCommand will respond to.
	 * This can be a normal string or a regular expression.
	 *
	 * @var string
	 */
	private $m_sTrigger;

	/**
	 * This property indicates whether the trigger is a regular expression
	 * or not. I want this so that I can use multiple and/or dynamic
	 * triggers.
	 *
	 * @var boolean
	 */
	private $m_bIsRegex;

	/**
	 * In order to let the different level make any difference at all, it is
	 * neccesary that some commands can only be executed by higher ranked
	 * people. We define the level needed for this command with this very
	 * property.
	 *
	 * @var integer
	 */
	private $m_nLevel;

	/**
	 * In here we have the callback to the code this LVPCommand executes.
	 *
	 * @var callback
	 */
	private $m_cCommand;

	/**
	 * The constructor creates a new LVPCommand ready for use as either IRC
	 * commands or as ingame commands. Provided with some information, the
	 * rest is setup automatically.
	 *
	 * @param string $trigger The trigger to listen to, this can be plain text or a regex.
	 * @param integer $level The minimum level required to execute this command.
	 * @param mixed $code A callback to the code we're executing or a string with the code itself.
	 * @throws Exception When $code is not a valid callback or has a syntax error.
	 */
	public function __construct($trigger, $level, $code) {
		if ($trigger[0] == '/') {
			$this->m_bIsRegex = true;
		}

		$this->m_sTrigger = $trigger;
		$this->m_nLevel   = $level;

		if (!is_callable($code)) {
			throw new Exception('Invalid callback supplied for command "' . $trigger . '".');
		}

		$this->m_cCommand = $code;
	}

	/**
	 * Returns the trigger this command responds to.
	 * @return string
	 */
	public function getTrigger() {
		return $this->m_sTrigger;
	}

	/**
	 * Whether this command responds to a regular expression.
	 * @return boolean
	 */
	public function isRegex() {
		return $this->m_bIsRegex;
	}

	/**
	 * Returns the level needed in order to successfully execute this command.
	 * @return integer
	 */
	public function getLevel() {
		return $this->m_nLevel;
	}

	/**
	 * This magic method is the most important method of the whole class.
	 * Not only will it allow us to just invoke the variable containing a
	 * pointer to this class, but immediately executing the code in here as
	 * well. Makes sense, in a way. It returns the return code of the
	 * callback we're executing in here as well as the actual output,
	 * seperated by a PHP_EOL. With the return code, we can determine what
	 * kind of message we got back, so we can use the standard wrapper
	 * methods back in the module.
	 *
	 * @param integer $outputMode The mode the command is executing in.
	 * @param integer $level The level we're operating at.
	 * @param string $channel The channel we're executing in.
	 * @param string $nickname The nickname of the person who triggered the command.
	 * @param string $trigger The trigger of the command.
	 * @param string $params All the params in one string.
	 * @param array $arrayParams All the params in an array, split by spaces.
	 * @return string
	 */
	public function __invoke($outputMode, $level, $channel, $nickname, $trigger, $params, $arrayParams) {
		if ($this->m_nLevel > $level) {
			throw new Exception("This command can only be executed with level " . $this->m_nLevel . " or higher.");
		}

		ob_start();

		$output = call_user_func($this->m_cCommand, $outputMode, $level, $channel, $nickname, $trigger, $params, $arrayParams);

		if ($output == null) {
			$output = self::OUTPUT_NORMAL;
		} else if (!is_int($output)) {
			$output = self::OUTPUT_NORMAL . PHP_EOL . trim($output);
		}

		return $output . PHP_EOL . trim(ob_get_clean());
	}
}
