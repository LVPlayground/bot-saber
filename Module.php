<?php
use Nuwani \ Bot;
use Nuwani \ BotManager;
use Nuwani \ Timer;

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
 * @author Dik Grapendaal <dik.grapendaal@gmail.com>
 *
 * $Id: Module.php 369 2014-01-20 21:53:00Z Dik $
 */
class LVPEchoHandler extends ModuleBase implements ArrayAccess
{

        /**
         * In this array we keep track of all the LVP channels and the levels
         * required to enter them.
         *
         * @var array
         */
        private $m_aLvpChannels;

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
         * A pointer to the MySQLi object with the actual LVP IP database will
         * be available in here.
         *
         * @var MySQLi
         */
        private $m_pDatabase;

        /**
         * In order to easily add settings for this getting-bigger-by-the-day-module,
         * I came up with a configuration manager. This can be controlled by one
         * command, and settings can easily be added when needed.
         */
        private $m_pConfiguration;

        /**
         * And yet another very important bit is right here. The command handler
         * will process all LVP related commands that this bot is expected to
         * handle.
         *
         * @var LVPCommandHandler
         */
        private $m_pCommandHandler;

        /**
         * This handler will handle anything to do with the LVP crew. This
         * includes keeping track of the crew, as well as seeing whether they
         * are ingame.
         *
         * @var LVPCrewHandler
         */
        private $m_pCrewHandler;

        /**
         * To keep things a bit seperated, the messages from the echo channel
         * will be parsed and the information gotten from that processed.
         *
         * @var LVPEchoMessageParser
         */
        private $m_pMessageParser;

        /**
         * As the name suggest, the LVPIpManager manages all stuff regarding IPs
         * for us. This includes resolving IPs to countries, cities and what
         * not. The !ipinfo and !ipcountry commands are handled through here as
         * well.
         *
         * @var LVPIpManager
         */
        private $m_pIpManager;

        /**
         * This manager will be used to manage all the LVPPlayer objects that
         * are floating around in this module.
         *
         * @var LVPPlayerManager
         */
        private $m_pPlayerManager;

        /**
         * Because we need to login to be able to get any data from the LVP
         * Trac, we'll assign a specific object to log us in and keep us logged
         * in, so that we can request data swiftly and painlessly.
         *
         * @var LVPTrac
         */
        private $m_pTrac;

        /**
         * An old feature of the previous bot were personalized welcome messages
         * when one joined and logged in to the server. This was moderately
         * populair, so I'm bringing it back.
         *
         * @var LVPWelcomeMessage
         */
        private $m_pWelcomeMessage;

        /**
         * An old feature of the previous bot were personalized welcome messages
         * when one joined and logged in to the server. This was moderately
         * populair, so I'm bringing it back.
         *
         * @var LVPLVPRadioHandler
         */
        private $m_pRadioHandler;

        /**
         * The constructor will prepare the module for immediate use.
         */
        public function __construct ()
        {
                if (!class_exists ('LVP'))
                {
                        require 'Sources/Vendor/geoipcity.php';
                        require 'Sources/Vendor/QuerySAMPServer.php';

                        require 'LVP.php';
                        require 'LVPDatabase.php';
                        require 'LVPEchoHandlerClass.php';
                        require 'LVPEchoMessageParser.php';
                        require 'LVPConfiguration.php';
                        require 'LVPCommandHandler.php';
                        require 'LVPCrewHandler.php';
                        require 'LVPIpManager.php';
                        require 'LVPPlayerManager.php';
                        require 'LVPTrac.php';
                        require 'LVPWelcomeMessage.php';
                        require 'LVPRadioHandler.php';
                }

                if (!is_dir ('Data/LVP'))
                {
                        mkdir ('Data/LVP', 0777);
                }

                $this->setNuwaniInfo(
                        array(
                                'Nuwani', 'Nuweni', 'Nuwini', 'Nuwoni', 'Nuwuni',
                                'Nowani', 'Noweni', 'Nowini', 'Nowoni', 'Nowuni',
                                'Nawani', 'Nawoni'
                        ),
                        array('sa-mp.nl', 'gtanet-37ag2t.sa-mp.nl', 'gtanet-hqt.8ca.192.82.IP')
                );

                $aLvpChannels = array
                (
                        '#LVP'                  => LVP :: LEVEL_NONE,
                        '#LVP.Beta'             => LVP :: LEVEL_NONE,
                        '#LVP.Beta.echo'        => LVP :: LEVEL_NONE,
                        '#LVP.crew'             => LVP :: LEVEL_MODERATOR,
                        '#LVP.Dev'              => LVP :: LEVEL_DEVELOPER,
                        '#LVP.echo'             => LVP :: LEVEL_NONE,
                        '#LVP.Managers'         => LVP :: LEVEL_MANAGEMENT,
                        '#LVP.Management'       => LVP :: LEVEL_MANAGEMENT,
                        '#LVP.NL'               => LVP :: LEVEL_NONE,
                        '#LVP.Radio'            => LVP :: LEVEL_NONE,
                        '#LVP.VIP'              => LVP :: LEVEL_VIP,
                        '#Bot'                  => LVP :: LEVEL_MANAGEMENT
                );

                foreach ($aLvpChannels as $sChannel => $nLevel)
                {
                        $this -> addLvpChannel ($nLevel, $sChannel);
                }

                $aCrewColors = array
                (
                        LVP :: LEVEL_MANAGEMENT         => '03',
                        LVP :: LEVEL_ADMINISTRATOR      => '04',
                        LVP :: LEVEL_MODERATOR          => '07',
                        LVP :: LEVEL_DEVELOPER          => '12'
                );

                $this -> m_nDatabaseTimerId = Timer :: create
                (
                        array (LVPDatabase :: getInstance (), 'ping'),
                        30000,
                        Timer :: INTERVAL
                );

                $this -> m_pCommandHandler      = new LVPCommandHandler         ($this);
                $this -> m_pConfiguration       = new LVPConfiguration          ($this);
                $this -> m_pCrewHandler         = new LVPCrewHandler            ($this, $aCrewColors);
                $this -> m_pMessageParser       = new LVPEchoMessageParser      ($this);
                $this -> m_pIpManager           = new LVPIpManager              ($this);
                $this -> m_pPlayerManager       = new LVPPlayerManager          ($this);
                $this -> m_pTrac                = new LVPTrac                   ($this);
                $this -> m_pWelcomeMessage      = new LVPWelcomeMessage         ($this);
                $this -> m_pRadioHandler        = new LVPRadioHandler           ($this);

                $this -> registerCommands ();
        }

        /**
         * This method registers some loose commands that don't belong in any
         * other classes. Most commands will probably make use of the anonymous
         * functions in PHP.
         */
        public function registerCommands ()
        {
                $this -> cmds -> register (new LVPCommand
                (
                        '!ses',
                        LVP :: LEVEL_MODERATOR,
                        function ($pModule, $nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams)
                        {
                                if ($sParams == null || !is_numeric ($aParams [0]))
                                {
                                        echo $sTrigger . ' ID';
                                        return LVPCommand :: OUTPUT_USAGE;
                                }

                                $pPlayer = $pModule ['Players'] -> getPlayer ($aParams [0]);
                                if ($pPlayer == null)
                                {
                                        echo 'ID not found.';
                                        return LVPCommand :: OUTPUT_ERROR;
                                }

                                if ($nMode == LVPCommand :: MODE_IRC)
                                {
                                        echo ModuleBase :: COLOUR_TEAL . '* Session length of "' .
                                                $pPlayer ['Nickname'] . '"' . ModuleBase :: CLEAR . ': ';
                                }

                                echo Func :: formatTime (time () - $pPlayer ['JoinTime']);
                        }
                ));

                $this -> cmds -> register (new LVPCommand
                (
                        '!syncplayers',
                        LVP :: LEVEL_MODERATOR,
                        function ($pModule, $nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams)
                        {
                                if ($pModule -> players -> syncPlayers ())
                                {
                                        echo ModuleBase::COLOUR_DARKGREEN . '* Succeeded.';
                                }
                                else
                                {
                                        echo ModuleBase::COLOUR_RED . '* Failed.';
                                }
                        }
                ));

                $this->cmds->register(new LVPCommand(
                        '!lvpdumpstate',
                        LVP::LEVEL_MANAGEMENT,
                        function ($pModule, $nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams) {
                                $pModule->players->saveState();
                                $pModule->crew->saveState();
                                echo ModuleBase::COLOUR_DARKGREEN . '* Done.';
                        }
                ));
        }

        /**
         * This method will register a channel as an official LVP channel in
         * which commands can be executed.
         *
         * @param integer $nLevel The minimum level required to enter this channel.
         * @param string $sName The actual channel name.
         */
        public function addLvpChannel ($nLevel, $sName)
        {
                $this -> m_aLvpChannels [strtolower ($sName)] = $nLevel;
        }

        /**
         * This method will add the given usernames and hostnames to the
         * internal array of credentials which are considered Nuwani bots.
         *
         * @param array $aUsername Array of possible usernames.
         * @param array $aHostname Array of possible hostnames.
         */
        public function setNuwaniInfo ($aUsername, $aHostname)
        {
                if ($this -> m_aNuwaniInfo == null)
                {
                        $this -> m_aNuwaniInfo = array
                        (
                                'Username' => array (),
                                'Hostname' => array ()
                        );
                }

                $this -> m_aNuwaniInfo ['Username'] = array_merge ($this -> m_aNuwaniInfo ['Username'], $aUsername);
                $this -> m_aNuwaniInfo ['Hostname'] = array_merge ($this -> m_aNuwaniInfo ['Hostname'], $aHostname);

                $this -> m_aNuwaniInfo ['Username'] = array_unique ($this -> m_aNuwaniInfo ['Username']);
                $this -> m_aNuwaniInfo ['Hostname'] = array_unique ($this -> m_aNuwaniInfo ['Hostname']);
        }

        /**
         * This method will return the level needed to join the given channel.
         * With this we'll determine what level people are when executing
         * commands.
         *
         * @param string $sChannel The channel we want to know the level from.
         * @return integer
         */
        public function getChannelLevel ($sChannel)
        {
                $sChannel = strtolower ($sChannel);
                if (isset ($this -> m_aLvpChannels [$sChannel]))
                {
                        return $this -> m_aLvpChannels [$sChannel];
                }

                return -1;
        }

        /**
         * The onChannelJoin callback will check if this bot has just joined
         * the LVP crew channel. If so, we will initiate a crew update, so we
         * can report any errors immediately.
         *
         * @param Bot $pBot The bot that joined the channel.
         * @param string $sChannel The channel that was joined.
         * @param stirng $sNickname The nickname that joined the channel.
         */
        public function onChannelJoin (Bot $pBot, $sChannel, $sNickname)
        {
                if ($sNickname != $pBot ['Nickname'])
                {
                        return ;
                }

                if (strtolower ($sChannel) != LVP :: CREW_CHANNEL)
                {
                        return ;
                }

                $this ['Commands'] -> handle ($pBot, $sChannel, $sNickname, '!updatecrew');
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
         * @param Bot $pBot The bot that received the message.
         * @param string $sChannel The channel we received this message in.
         * @param string $sNickname The nickname from which we got this message.
         * @param string $sMessage And of course the message itself.
         */
        public function onChannelPrivmsg (Bot $pBot, $sChannel, $sNickname, $sMessage)
        {
                if ($pBot ['Network'] != LVP :: NETWORK)
                {
                        // Other network than where LVP is, so we'll bail out.
                        return ;
                }

                if (!isset ($this -> m_aLvpChannels [strtolower ($sChannel)]))
                {
                        // Not an LVP channel either.
                        return ;
                }

                if (in_array ($pBot -> In -> User -> Hostname, $this -> m_aNuwaniInfo ['Hostname']))
                {
                        if (strtolower ($sChannel) == LVP :: ECHO_CHANNEL)
                        {
                                // A message from a Nuwani bot in the echo channel.
                                $this ['Parser'] -> parse ($pBot, $sMessage);
                        }

                        if (substr ($sMessage, 0, 14) == '4Message from')
                        {
                                $iColonPos = strpos ($sMessage, chr (3) . ': ');
                                $sMessage  = substr ($sMessage, $iColonPos + 3);
                                $sNickname = substr ($sMessage, 15, $iColonPos + 15);

                                // We'll "fall through" to the command handler automatically.
                        }
                }
                else if ($pBot -> In -> User -> Hostname == 'gtanet-k5eumq.q9b4.45gs.1af8.2001.IP')
                {
                        // Let the RadioHandler process this message
                        $this ['Radio'] -> processChannelMessage ($sMessage);
                }

                $this ['Commands'] -> handle ($pBot, $sChannel, $sNickname, $sMessage);
        }

        /**
         * Access to the several LVPEchoHandler classes is now also possible
         * using the property syntax. These do use an abbreviated form however,
         * so that it's much shorter than the ArrayAccess syntax.
         *
         * @param string $sProperty The abbreviated name of the class we want to access.
         * @return mixed
         */

        public function __get ($sProperty)
        {
                switch ($sProperty)
                {
                        case 'db':              { return LVPDatabase :: getInstance (); }
                        case 'config':          { return $this -> m_pConfiguration;     }
                        case 'crew':            { return $this -> m_pCrewHandler;       }
                        case 'parser':          { return $this -> m_pMessageParser;     }
                        case 'cmds':            { return $this -> m_pCommandHandler;    }
                        case 'ip':              { return $this -> m_pIpManager;         }
                        case 'players':         { return $this -> m_pPlayerManager;     }
                        case 'trac':            { return $this -> m_pTrac;              }
                        case 'welcomemsg':      { return $this -> m_pWelcomeMessage;    }
                }

                return false;
        }

        /**
         * I abuse the ArrayAccess interface to quickly add some getters for the
         * several handlers, manager and what not classes in this module.
         *
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         * @return boolean
         */
        public function offsetExists ($mKey)
        {
                return $this -> offsetGet ($mKey) !== false;
        }

        /**
         * I abuse the ArrayAccess interface to quickly add some getters for the
         * several handlers, manager and what not classes in this module. These
         * include but are not limited to: Database, Crew, Parser and Commands.
         * Those names seem pretty obvious to me as to what they'll return. If
         * not, look in the code below.
         *
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         * @return mixed
         */
        public function offsetGet ($mKey)
        {
                switch ($mKey)
                {
                        case 'db':              case 'Database':        { return LVPDatabase :: getInstance (); }
                        case 'config':          case 'Configuration':   { return $this -> m_pConfiguration;     }
                        case 'crew':            case 'Crew':            { return $this -> m_pCrewHandler;       }
                        case 'parser':          case 'Parser':          { return $this -> m_pMessageParser;     }
                        case 'cmds':            case 'Commands':        { return $this -> m_pCommandHandler;    }
                        case 'ip':              case 'IP':              { return $this -> m_pIpManager;         }
                        case 'players':         case 'Players':         { return $this -> m_pPlayerManager;     }
                        case 'trac':            case 'Trac':            { return $this -> m_pTrac;              }
                        case 'welcomemsg':      case 'WelcomeMessage':  { return $this -> m_pWelcomeMessage;    }
                        case 'radio':           case 'Radio':           { return $this -> m_pMessageParser;     }
                }

                return false;
        }

        /**
         * I abuse the ArrayAccess interface to quickly add some getters for the
         * several handlers, manager and what not classes in this module.
         *
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         * @param mixed $mValue The value supplied when setting something.
         */
        public function offsetSet ($mKey, $mValue)
        {
                throw new Exception ("Not supported");
        }

        /**
         * I abuse the ArrayAccess interface to quickly add some getters for the
         * several handlers, manager and what not classes in this module.
         *
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         */
        public function offsetUnset ($mKey)
        {
                throw new Exception ("Not supported");
        }

        /**
         * This method will quickly look up a bot which we can use to send
         * information to a specific channel.
         *
         * @param string $sChannel The channel in which we need a bot.
         * @throws Exception when no bots were found.
         * @return Bot
         */
        public function getBot ($sChannel)
        {
                $pBot = BotManager :: getInstance () -> offsetGet ('network:' . LVP :: NETWORK . ' channel:' . $sChannel);

                if ($pBot instanceof BotGroup)
                {
                        if (count ($pBot) == 0)
                        {
                                // Wait. That's not right.
                                throw new Exception ('No bot could be found which is connected to network "' . LVP :: NETWORK . '" and in channel ' . $sChannel);
                        }
                        else if (count ($pBot) > 1)
                        {
                                $pBot = $pBot -> seek (0);
                        }
                }

                return $pBot;
        }

        /**
         * Sends a message to a destination, this can be a channel or a
         * nickname.
         *
         * @param Bot $pBot The bot to send it with.
         * @param string $sDestination The destination.
         * @param string $sMessage The actual message to send.
         * @param integer $nMode The mode the output should be processed at.
         */
        public function privmsg ($pBot, $sDestination, $sMessage, $nMode = null)
        {
                if ($pBot == null)
                {
                        $pBot = $this -> getBot ($sDestination);
                }

                if ($nMode == null)
                {
                        $nMode = LVPCommand :: MODE_IRC;
                }

                foreach (explode (PHP_EOL, $sMessage) as $sMessage)
                {
                        $sMessage = trim ($sMessage);

                        if ($nMode != LVPCommand :: MODE_IRC)
                        {
                                $sMessage = Util :: stripFormat ($sMessage);

                                if (substr ($sMessage, 0, 2) == '* ')
                                {
                                        $sMessage = substr ($sMessage, 2);
                                }

                                $sPrefix = '!admin ';
                                if ($nMode == LVPCommand :: MODE_MAIN_CHAT)
                                {
                                        $sPrefix = '!msg ';
                                }

                                $sMessage = $sPrefix . $sMessage;
                        }

                        $pBot -> send ('PRIVMSG ' . $sDestination . ' :' . $sMessage);
                }

                return true;
        }

        /**
         * Sends an error message to the destination. The message is prefixed
         * with '* Error:' in red, to distinguish the message from messages
         * which provide generally better news for the users.
         *
         * @param Bot $pBot The bot to send it with.
         * @param string $sDestination The destination.
         * @param string $sMessage The actual message to send.
         * @param integer $nMode The mode the output should be processed at.
         */
        public function error ($pBot, $sDestination, $sMessage, $nMode = null)
        {
                if ($sDestination != LVP :: DEBUG_CHANNEL)
                {
                        $sDestination .= ',' . LVP :: DEBUG_CHANNEL;
                }

                return $this -> privmsg ($pBot, $sDestination, '4* Error: ' . $sMessage, $nMode);
        }

        /**
         * Sends a general informational message to the user. The '* Info'
         * prefix message has a soft blue-ish color to indicate that's not a big
         * deal, unlike error messages.
         *
         * @param Bot $pBot The bot to send it with.
         * @param string $sDestination The destination.
         * @param string $sMessage The actual message to send.
         * @param integer $nMode The mode the output should be processed at.
         */
        public function info ($pBot, $sDestination, $sMessage, $nMode = null)
        {
                return $this -> privmsg ($pBot, $sDestination, '10* Info: ' . $sMessage, $nMode);
        }

        /**
         * Sends a message that requires the attention of the user, but is not
         * critical, unlike error messages. The '* Notice' prefix message has an
         * orange color to indicate that attention is required, but everything
         * will continue to work.
         *
         * @param Bot $pBot The bot to send it with.
         * @param string $sDestination The destination.
         * @param string $sMessage The actual message to send.
         * @param integer $nMode The mode the output should be processed at.
         */
        public function notice ($pBot, $sDestination, $sMessage, $nMode = null)
        {
                return $this -> privmsg ($pBot, $sDestination, '7* Notice: ' . $sMessage, $nMode);
        }

        /**
         * Sends a message to the user indicating that something worked out
         * nicely. The '* Success' prefix message has a green color to indicate
         * that all's good.
         *
         * @param Bot $pBot The bot to send it with.
         * @param string $sDestination The destination.
         * @param string $sMessage The actual message to send.
         * @param integer $nMode The mode the output should be processed at.
         */
        public function success ($pBot, $sDestination, $sMessage, $nMode = null)
        {
                return $this -> privmsg ($pBot, $sDestination, '3* Success: ' . $sMessage, $nMode);
        }

        /**
         * Sends an informational message to the user about how to use a certain
         * command. The '* Usage' prefix message has a soft blue-ish color to
         * indicate that's not a big deal, unlike error messages.
         *
         * @param Bot $pBot The bot to send it with.
         * @param string $sDestination The destination.
         * @param string $sMessage The actual message to send.
         * @param integer $nMode The mode the output should be processed at.
         */
        public function usage ($pBot, $sDestination, $sMessage, $nMode = null)
        {
                return $this -> privmsg ($pBot, $sDestination, '10* Usage: ' . $sMessage, $nMode);
        }
}
?>
