<?php
use Nuwani\Bot;
use Nuwani\Timer;

/**
 * LVPEchoHandler module for Nuwani v2
 *
 * This module is a rewrite of the basic functionality of my old IRC bot. The
 * bot monitored every single message in the LVP echo channel, parsed them and
 * extracted information from them. It worked quite well, however, as time
 * passed, more and more bugs came to the surface, which kept being fixed by
 * either work-arounds or disabling some functionality altogether by commenting
 * it out.
 *
 * I've been planning this rewrite for quite some time now, and decided it would
 * be best to make it as general as possible, so it can be plugged into any
 * Nuwani v2 bot and have general information about the LVP server handy at all
 * times, while anybody can make their own commands and functionality with it.
 *
 * Commands are implemented by using the LVPCommand class and registering them
 * to the LVPCommandHandler. The advantage of this is that the commands can be
 * executed from about anywhere, from IRC channels, as well as the crew chat in
 * the LVP gameserver.
 *
 * Other functionality includes keeping track of the LVP crew. A feed with all
 * the crew names in it is retrieved when the bot joins the LVP crew channel,
 * and will be able to determine the rights of a person ingame with that. Rights
 * on IRC are determined by what channel a command is executed in. A command
 * only for management will only be available in the management channels, but a
 * crew command will be available will also be available in crew channels.
 * Public commands are available in every LVP channel. Note that this "public"
 * is still limited to LVP channels only.
 *
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPEchoHandler extends ModuleBase {

	/**
	 * An multidimensional array with all the possible idents and hostnames
	 * the Nuwani bots may have. All messages coming from sources that don't
	 * match with at least one ident and hostname, will be ignored as a
	 * Nuwani message.
	 *
	 * @var array
	 */
	private $m_aNuwaniInfo;

	/**
	 * In here we store the identification string of the timer that's
	 * checking the database connection.
	 *
	 * @var string
	 */
	private $m_nDatabaseTimerId;

	/**
	 * A reference to the MySQLi object with the actual LVP database will
	 * be available in here.
	 *
	 * @var LVPDatabase
	 */
	private $Database;

	/**
	 * The IRC service helps in communicating results from processing back to
	 * people on... well, IRC.
	 *
	 * @var LVPIrcService
	 */
	private $IrcService;

	/**
	 * In order to easily add settings for this module, I came up with a
	 * configuration manager. This can be controlled by one command, and
	 * settings can easily be added when needed.
	 *
	 * @var LVPConfiguration
	 */
	private $Configuration;

	/**
	 * And yet another very important bit is right here. The command handler
	 * will process all LVP related commands that this bot is expected to
	 * handle.
	 *
	 * @var LVPCommandHandler
	 */
	private $CommandService;

	/**
	 * This handler will handle anything to do with the LVP crew. This
	 * includes keeping track of the crew, as well as seeing whether they
	 * are ingame.
	 *
	 * @var LVPCrewHandler
	 */
	private $CrewService;

	/**
	 * Messages from the echo channel will be parsed by this module and the
	 * information from those messages will be passed on to other modules so
	 * that useful stuff can be done. Most generic description of any piece of
	 * code ever. Well done.
	 *
	 * @var LVPEchoMessageParser
	 */
	private $EchoMessageParser;

	/**
	 * As the name suggests, the LVPIpManager manages all stuff regarding IPs
	 * for us. This includes resolving IPs to countries, cities and what
	 * not. The !ipinfo and !ipcountry commands are handled through here as
	 * well.
	 *
	 * @var LVPIpManager
	 */
	private $IpService;

	/**
	 * This manager will be used to manage all the LVPPlayer objects that
	 * are floating around in this module.
	 *
	 * @var LVPPlayerManager
	 */
	private $PlayerService;

	/**
	 * An old feature of the previous bot were personalized welcome messages
	 * when one joined and logged in to the server. This was moderately
	 * popular, so I'm bringing it back. Me to past self: DID YOU REALLY.
	 *
	 * @var LVPWelcomeMessage
	 */
	private $WelcomeMessageService;

	/**
	 * The radio service facilitates all commands and behavior relating to the
	 * amazing LVP Radio, which can be controlled by the community themselves.
	 *
	 * @var LVPRadioHandler
	 */
	private $RadioService;

	/**
	 * The constructor will prepare the module for immediate use.
	 */
	public function __construct() {
		if (!class_exists('LVP')) {
			// Third party
			require 'Sources/Vendor/geoipcity.php';
			require 'Sources/Vendor/QuerySAMPServer.php';
			// Our own stuff
			require 'LVP.php';
			require 'LVPDatabase.php';
			require 'LVPCommandRegistrar.php';
			require 'LVPEchoMessageParser.php';
			require 'LVPConfiguration.php';
			require 'LVPCommandHandler.php';
			require 'LVPCrewHandler.php';
			require 'LVPIpManager.php';
			require 'LVPIrcService.php';
			require 'LVPPlayerManager.php';
			require 'LVPWelcomeMessage.php';
			require 'LVPRadioHandler.php';
		}

		if (!is_dir('Data/LVP')) {
			mkdir('Data/LVP', 0777);
		}

		$this->Database = new LVPDatabase();
		$this->IrcService = new LVPIrcService();
		$this->CommandService = new LVPCommandHandler($this->IrcService);
		$this->Configuration = new LVPConfiguration();
		$this->PlayerService = new LVPPlayerManager($this->Database);
		$this->CrewService = new LVPCrewHandler($this->Database, $this->IrcService, $this->PlayerService);
		$this->IpService = new LVPIpManager($this->Database, $this->PlayerService);
		$this->WelcomeMessageService = new LVPWelcomeMessage($this->IrcService);
		$this->EchoMessageParser = new LVPEchoMessageParser($this->Configuration, $this->CommandService,
			$this->CrewService, $this->IpService, $this->IrcService, $this->PlayerService, $this->WelcomeMessageService);
		$this->RadioService = new LVPRadioHandler($this->IrcService);

		// Ping the database connection every 30 seconds. Reconnect if needed.
		$this->m_nDatabaseTimerId = Timer::create(
			array($this, 'pingDatabase'),
			30000,
			Timer::INTERVAL
		);

		$this->setNuwaniInfo(
			array(
				'Nuwani', 'Nuweni', 'Nuwini', 'Nuwoni', 'Nuwuni',
				'Nowani', 'Noweni', 'Nowini', 'Nowoni', 'Nowuni',
				'Nawani', 'Nawoni'
			),
			array('sa-mp.nl', 'gtanet-37ag2t.sa-mp.nl', 'gtanet-hqt.8ca.192.82.IP')
		);

		$lvpChannels = array(
			'#LVP'            => LVP::LEVEL_NONE,
			'#LVP.crew'       => LVP::LEVEL_ADMINISTRATOR,
			'#LVP.echo'       => LVP::LEVEL_NONE,
			'#LVP.Managers'   => LVP::LEVEL_MANAGEMENT,
			'#LVP.Management' => LVP::LEVEL_MANAGEMENT,
			'#LVP.NL'         => LVP::LEVEL_NONE,
			'#LVP.VIP'        => LVP::LEVEL_VIP,
			// Private debug channel
			'#Bot'            => LVP::LEVEL_MANAGEMENT
		);

		foreach ($lvpChannels as $channel => $level) {
			$this->IrcService->addLvpChannel($level, $channel);
		}

		$this->registerCommands();
	}

	/**
	 * This method registers some loose commands that don't belong in any
	 * other classes. Most commands will probably make use of the anonymous
	 * functions in PHP.
	 */
	public function registerCommands() {
		$this->Configuration->registerCommands($this->CommandService);
		$this->CrewService->registerCommands($this->CommandService);
		$this->IpService->registerCommands($this->CommandService);

		$this->CommandService->register(new LVPCommand(
			'!ses',
			LVP::LEVEL_ADMINISTRATOR,
			function ($nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams) {
				if ($sParams == null || !is_numeric($aParams[0])) {
					echo $sTrigger . ' ID';
					return LVPCommand::OUTPUT_USAGE;
				}

				$pPlayer = $this->PlayerService->getPlayer($aParams[0]);
				if ($pPlayer == null) {
					echo 'ID not found.';
					return LVPCommand::OUTPUT_ERROR;
				}

				if ($nMode == LVPCommand::MODE_IRC) {
					echo ModuleBase::COLOUR_TEAL . '* Session length of "' .
						$pPlayer['Nickname'] . '"' . ModuleBase::CLEAR . ': ';
				}

				echo Util::formatTime(time() - $pPlayer['JoinTime']);
			}
		));

		$this->CommandService->register(new LVPCommand(
			'!syncplayers',
			LVP::LEVEL_ADMINISTRATOR,
			function ($nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams) {
				if ($this->PlayerService->syncPlayers() && $this->CrewService->syncPlayers()) {
					echo ModuleBase::COLOUR_DARKGREEN . '* Succeeded.';
				} else {
					echo ModuleBase::COLOUR_RED . '* Failed.';
				}
			}
		));

		$this->CommandService->register(new LVPCommand(
			'!lvpdumpstate',
			LVP::LEVEL_MANAGEMENT,
			function ($nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams) {
				$this->PlayerService->saveState();
				$this->CrewService->saveState();
				echo ModuleBase::COLOUR_DARKGREEN . '* Done.';
			}
		));
	}

	/**
	 * This method will add the given usernames and hostnames to the
	 * internal array of credentials which are considered Nuwani bots.
	 *
	 * @param array $aUsername Array of possible usernames.
	 * @param array $aHostname Array of possible hostnames.
	 */
	public function setNuwaniInfo($aUsername, $aHostname) {
		if ($this->m_aNuwaniInfo == null) {
			$this->m_aNuwaniInfo = array(
				'Username' => array(),
				'Hostname' => array()
			);
		}

		$this->m_aNuwaniInfo['Username'] = array_merge($this->m_aNuwaniInfo['Username'], $aUsername);
		$this->m_aNuwaniInfo['Hostname'] = array_merge($this->m_aNuwaniInfo['Hostname'], $aHostname);

		$this->m_aNuwaniInfo['Username'] = array_unique($this->m_aNuwaniInfo['Username']);
		$this->m_aNuwaniInfo['Hostname'] = array_unique($this->m_aNuwaniInfo['Hostname']);
	}

	/**
	 * Pings the database connection to see if it's still alive. If not, it will
	 * reconnect.
	 */
	public function pingDatabase() {
		if (!$this->Database->ping()) {
			$this->Database->close();
			$this->Database = new LVPDatabase();
		}
	}

	/**
	 * The onChannelJoin callback will check if this bot has just joined a specific LVP
	 * channel. If so, we will execute some specific commands to make sure the handler is up
	 * to date.
	 * For #LVP.Crew it executes the !updatecrew-cmd to have !ingamecrew up to date
	 * For #LVP.Radio it executes the !dj-cmd publicly to know the current dj
	 *
	 * @param Bot    $bot      The bot that joined the channel.
	 * @param string $channel  The channel that was joined.
	 * @param string $nickname The nickname that joined the channel.
	 */
	public function onChannelJoin(Bot $bot, string $channel, string $nickname) {
		if ($bot['Network'] != LVP::NETWORK || $nickname != $bot['Nickname']) {
			// Don't execute if we're not on the correct network if this join
			// event is not from ourselves.
			return;
		}

		if (strtolower($channel) == LVP::CREW_CHANNEL) {
			// TODO Nice hardcoding of our own command name there mate. Do this properly.
			$this->CommandService->handle($bot, $channel, $nickname, '!updatecrew');
		}

		if (strtolower($channel) == LVP::RADIO_CHANNEL) {
			$this->privmsg($bot, LVP::RADIO_CHANNEL, '!dj');
		}
	}

	/**
	 * This callback will process all the incoming channel messages. If it's
	 * a message coming from a Nuwani bot, it will be dispatched to the LVP
	 * message handler, where important information is extracted.
	 *
	 * After that, the message is checked for commands. These are LVP
	 * specific commands, so we also check whether they are executed in the
	 * right channel.
	 *
	 * In the case the message is not from Nuwani but from the radiobot, we send the message to
	 * the radiohandler to handle it over there.
	 *
	 * @param Bot $pBot The bot that received the message.
	 * @param string $sChannel The channel we received this message in.
	 * @param string $sNickname The nickname from which we got this message.
	 * @param string $sMessage And of course the message itself.
	 */
	public function onChannelPrivmsg(Bot $pBot, $sChannel, $sNickname, $sMessage) {
		if ($pBot['Network'] != LVP::NETWORK) {
			// Other network than where LVP is, so we'll bail out.
			return;
		}

		$lowerChannel = strtolower($sChannel);

		if (!$this->IrcService->isLvpChannel($sChannel)) {
			if ($lowerChannel == LVP::RADIO_CHANNEL) {
				// Let the RadioHandler process this message
				$this->RadioService->processChannelMessage($sNickname, $sMessage);
			}
			return;
		}


		if (in_array($pBot->In->User->Hostname, $this->m_aNuwaniInfo['Hostname'])) {
			if ($lowerChannel == LVP::ECHO_CHANNEL) {
				// A message from a Nuwani bot in the echo channel.
				$this->EchoMessageParser->parse($pBot, $sMessage);
			}

			if (substr($sMessage, 0, 14) == '4Message from') {
				$iColonPos = strpos($sMessage, chr (3) . ': ');
				$sMessage = substr($sMessage, $iColonPos + 3);
				$sNickname = substr($sMessage, 15, $iColonPos + 15);

				// We'll "fall through" to the command handler automatically.
			}
		}

		$this->CommandService->handle($pBot, $sChannel, $sNickname, $sMessage);
	}

	/**
	 * Received when someone privately messages the bot. We get a message from the radiobot
	 * when someone with the correct rights executed the !stopautodj-command.
	 *
	 * @param Bot    $bot Object of the bot who received it
	 * @param string $nickname Nickname of the user who send the message privately
	 * @param string $message Message which we received from the user
	 */
	public function onPrivmsg(Bot $bot, string $nickname, string $message) {
		if ($bot['Network'] == LVP::NETWORK && strtolower($nickname) == LVPRadioHandler::RADIO_BOT_NAME) {
			// Let the RadioHandler process this private message
			$this->RadioService->processPrivateMessage($message);
		}
	}

	/**
	 * Received when the bot joins a channel or when the bot itself send this command, the last
	 * one is done by the RadioHandler when the !stopautodj-command is executed.
	 *
	 * @param Bot    $bot Object of the bot who received it
	 * @param string $channel Name of the channel where names are received from
	 * @param string $names Space-seperated string of userrights and the username
	 */
	public function onChannelNames(Bot $bot, string $channel, string $names) {
		if ($bot['Network'] == LVP::NETWORK && strtolower($channel) == LVP::RADIO_CHANNEL) {
			// Let the RadioHandler check the names
			$this->RadioService->handleNamesChecking(explode(' ', $names));
		}
	}

	/**
	 * This method is called every time the main loop iterates, so we can use it
	 * to check on any running asynchronous SQL queries.
	 */
	public function onTick() {
		// $links = array($this->db);
		// $processed = 0;
		// do {
		// 	$read = $error = $reject = array();
		// 	foreach ($links as $link) {
		// 		$read[] = $error[] = $reject[] = $link;
		// 	}

		// 	if (!mysqli::poll($read, $error, $reject, 1)) {
		// 		continue;
		// 	}

		// 	foreach ($read as $link) {
		// 		if ($result = $link->reap_async_query()) {
		// 			// TODO what do with result
		// 			print_r($result->fetch_row());
		// 			if (is_object($result)) {
		// 				$result->free_result();
		// 			}
		// 		} else {
		// 			$this->error(null, LVP::DEBUG_CHANNEL, 'Error: ' . $link->error);
		// 		}
		// 		$processed++;
		// 	}
		// } while ($processed < count($links));
	}
}
