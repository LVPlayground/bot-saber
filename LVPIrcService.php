<?php
use Nuwani\BotGroup;
use Nuwani\BotManager;

/**
 * LVPEchoHandler module for Nuwani v2
 *
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPIrcService {

	/**
	 * In this array we keep track of all the LVP channels and the levels
	 * required to enter them.
	 *
	 * @var array
	 */
	private $lvpChannels;

	/**
	 * This method will register a channel as an official LVP channel in
	 * which commands can be executed.
	 *
	 * @param integer $level The minimum level required to enter this channel.
	 * @param string $name The actual channel name.
	 */
	public function addLvpChannel($level, $name) {
		$this->lvpChannels[strtolower($name)] = $level;
	}

	/**
	 * Tells us whether the given channel name is an actual LVP channel.
	 * 
	 * @param string $channel The channel name to check.
	 * @return boolean
	 */
	public function isLvpChannel($channel) {
		return isset($this->lvpChannels[strtolower($channel)]);
	}

	/**
	 * This method will return the level needed to join the given channel.
	 * With this we'll determine what level people are when executing
	 * commands.
	 *
	 * @param string $channel The channel we want to know the level from.
	 * @return integer
	 */
	public function getChannelLevel($channel) {
		$channel = strtolower($channel);
		if (isset($this->lvpChannels[$channel])) {
			return $this->lvpChannels[$channel];
		}

		return -1;
	}

	/**
	 * This method will quickly look up a bot which we can use to send
	 * information to a specific channel.
	 *
	 * @param string $channel The channel in which we need a bot.
	 * @throws Exception when no bots were found.
	 * @return Bot
	 */
	public function getBot($channel) {
		$bot = BotManager::getInstance()->offsetGet('network:' . LVP::NETWORK . ' channel:' . $channel);

		if ($bot instanceof BotGroup) {
			if (count($bot) == 0) {
				// Wait. That's not right.
				throw new Exception('No bot could be found which is connected to network "' .
					LVP::NETWORK . '" and in channel ' . $channel);
			}
			else if (count($bot) > 1) {
				$bot = $bot->seek(0);
			}
		}

		return $bot;
	}

	/**
	 * Sends a message to a destination, this can be a channel or a
	 * nickname.
	 *
	 * @param Bot $bot The bot to send it with.
	 * @param string $destination The destination.
	 * @param string $message The actual message to send.
	 * @param integer $outputMode The mode the output should be processed at.
	 */
	public function privmsg($bot, $destination, $message, $outputMode = null) {
		if ($bot == null) {
			$bot = $this->getBot($destination);
		}

		if ($outputMode == null) {
			$outputMode = LVPCommand::MODE_IRC;
		}

		foreach (explode (PHP_EOL, $message) as $message) {
			$message = trim($message);
			if ($message === '') {
				// Skip empty lines
				continue;
			}

			if ($outputMode != LVPCommand::MODE_IRC) {
				$message = Util::stripFormat($message);

				if (substr($message, 0, 2) == '* ') {
					$message = substr($message, 2);
				}

				$prefix = '!admin ';
				if ($outputMode == LVPCommand::MODE_MAIN_CHAT) {
					$prefix = '!msg ';
				}

				$message = $prefix . $message;
			}

			$bot->send('PRIVMSG ' . $destination . ' :' . $message);
		}

		return true;
	}

	/**
	 * Sends an error message to the destination. The message is prefixed
	 * with '* Error:' in red, to distinguish the message from messages
	 * which provide generally better news for the users.
	 *
	 * @param Bot $bot The bot to send it with.
	 * @param string $destination The destination.
	 * @param string $message The actual message to send.
	 * @param integer $outputMode The mode the output should be processed at.
	 */
	public function error($bot, $destination, $message, $outputMode = null) {
		if ($destination != LVP::DEBUG_CHANNEL) {
			$destination .= ',' . LVP::DEBUG_CHANNEL;
		}

		return $this->privmsg($bot, $destination, '4* Error: ' . $message, $outputMode);
	}

	/**
	 * Sends a general informational message to the user. The '* Info'
	 * prefix message has a soft blue-ish color to indicate that's not a big
	 * deal, unlike error messages.
	 *
	 * @param Bot $bot The bot to send it with.
	 * @param string $destination The destination.
	 * @param string $message The actual message to send.
	 * @param integer $outputMode The mode the output should be processed at.
	 */
	public function info($bot, $destination, $message, $outputMode = null) {
		return $this->privmsg($bot, $destination, '10* Info: ' . $message, $outputMode);
	}

	/**
	 * Sends a message that requires the attention of the user, but is not
	 * critical, unlike error messages. The '* Notice' prefix message has an
	 * orange color to indicate that attention is required, but everything
	 * will continue to work.
	 *
	 * @param Bot $bot The bot to send it with.
	 * @param string $destination The destination.
	 * @param string $message The actual message to send.
	 * @param integer $outputMode The mode the output should be processed at.
	 */
	public function notice($bot, $destination, $message, $outputMode = null) {
		return $this->privmsg($bot, $destination, '7* Notice: ' . $message, $outputMode);
	}

	/**
	 * Sends a message to the user indicating that something worked out
	 * nicely. The '* Success' prefix message has a green color to indicate
	 * that all's good.
	 *
	 * @param Bot $bot The bot to send it with.
	 * @param string $destination The destination.
	 * @param string $message The actual message to send.
	 * @param integer $outputMode The mode the output should be processed at.
	 */
	public function success($bot, $destination, $message, $outputMode = null) {
		return $this->privmsg($bot, $destination, '3* Success: ' . $message, $outputMode);
	}

	/**
	 * Sends an informational message to the user about how to use a certain
	 * command. The '* Usage' prefix message has a soft blue-ish color to
	 * indicate that's not a big deal, unlike error messages.
	 *
	 * @param Bot $bot The bot to send it with.
	 * @param string $destination The destination.
	 * @param string $message The actual message to send.
	 * @param integer $outputMode The mode the output should be processed at.
	 */
	public function usage($bot, $destination, $message, $outputMode = null) {
		return $this->privmsg($bot, $destination, '10* Usage: ' . $message, $outputMode);
	}
}