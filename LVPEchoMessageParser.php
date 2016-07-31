<?php
use Nuwani \ Bot;

/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPEchoMessageParser extends LVPEchoHandlerClass
{
        
        /**
         * In order to filter Nuwani's output in the echo channel as best as we
         * can, I used something that was called the "shit triggers" in the
         * previous bot. However, I think that's a bit inappropriate, and
         * therefore I'm calling this the Ignore Triggers. These are the
         * triggers we will ignore at all times and thus will not be parsed by
         * this class.
         * 
         * @var array
         */
        private $m_aIgnoreTriggers;
        
        /**
         * The constructor has been extended so that we can initialize this
         * class a bit. At the moment there's only the array which defines what
         * triggers can be ignored from the Nuwani bots, but I'm sure this is
         * going to be extended in the future.
         * 
         * @param LVPEchoHandler $pModule The LVPEchoHandler module we're residing in.
         */
        public function __construct (LVPEchoHandler $pModule)
        {
                parent :: __construct ($pModule);
                
                $this -> m_aIgnoreTriggers = array
                (
                        '7Online', '4Upload', '6***',
                        '6Matches', '4Error:', '4Notice:'
                );
                
                $this -> m_pModule -> config -> addDirective ('parser', 'welcomemsg', true, FILTER_VALIDATE_BOOLEAN);
                $this -> m_pModule -> config -> addDirective ('parser', 'reports',    true, FILTER_VALIDATE_BOOLEAN);
        }
        
        /**
         * What's a parser without a parse method? Exactly, nothing much. So...
         * here it is. This method will happily parse our LVP echo channel
         * message and extract the needed informations. These will then be put 
         * to use in other methods.
         * 
         * @var Bot $pBot The bot which received the message.
         * @var string $sMessage The actual message we're going to parse.
         */
        public function parse (Bot $pBot, $sMessage)
        {
                $aChunks = preg_split ('/\s+/', $sMessage);
                
                switch ($aChunks [0])
                {
                        case '05***':
                        {
                                $this -> handleAdminMessage ($pBot, substr ($sMessage, 7), $aChunks);
                                break;
                        }
                        
                        case '2***':
                        {
                                if ($aChunks [1] == '7Admin' || $aChunks [1] == '7Report')
                                {
                                        $this -> handleCrewChat ($pBot, $aChunks [2], implode (' ', array_slice ($aChunks, 4)), $aChunks);
                                }
                                break;
                        }
                        
                        case '4***':
                        {
                                if ($aChunks [1] == 'Global' && $aChunks [2] == 'Gamemode' && $aChunks [3] == 'Initialization')
                                {
                                        $this -> handleGamemodeInit ();
                                }
                                break;
                        }
                        
                        case '4IP':
                        {
                                $nId = substr ($aChunks [3], 4, -2);
                                $this -> handleIpMessage ($pBot, $nId, $aChunks [2], $aChunks [4], $aChunks);
                                break;
                        }
                }
                
                if (!isset ($aChunks [1]))
                {
                        return ;
                }
                
                switch ($aChunks [1])
                {
                        case '03***':
                        case '3***':
                        {
                                $nId = substr (Func :: stripFormat ($aChunks [0]), 1, -1);
                                if ($aChunks [3] == 'joined')
                                {
                                        $this -> handleJoinGame ($pBot, $nId, $aChunks [2], $aChunks);
                                }
                                else if ($aChunks [3] == 'left')
                                {
                                        $this -> handleLeaveGame ($pBot, $nId, $aChunks [2], substr ($aChunks [6], 1, -3), $aChunks);
                                }
                                else if ($aChunks [4] == 'logged')
                                {
                                        $this -> handleLoggedIn ($pBot, $nId, $aChunks [2], $aChunks);
                                }
                                break;
                        }
                        
                        default:
                        {
                                if (substr ($aChunks [1], 0, 3) == '07' &&
                                    substr ($aChunks [1], -2) == ':')
                                {
                                        $nId       = substr (Util :: stripFormat ($aChunks [0]), 1, -1);
                                        $sNickname = substr (Util :: stripFormat ($aChunks [1]), 0, -1);
                                        
                                        $this -> handleMainChatMessage ($pBot, $nId, $sNickname, implode (' ', array_slice ($aChunks, 2)), $aChunks);
                                }
                                break;
                        }
                }
                
                /* Enable this when going to save the echo logs.
                if (in_array ($aChunks [0], $this -> m_aIgnoreTriggers))
                {
                        return ;
                }
                
                if (count ($aChunks) == count (explode (', ', implode (' ', $aChunks))))
                {
                        // Filter out the !players command.
                        return ;
                }
                
                if (Func :: getPieces ($aChunks, ' ', 0, 3) == 'has been online for' ||
                    Func :: getPieces ($aChunks, ' ', 0, 2) == 'is a player' ||
                    Func :: getPieces ($aChunks, ' ', 0, 4) == 'requested player is not registered')
                {
                        return ;
                }
                */
        }
        
        /**
         * This method will be triggered when a general administrator message
         * came along. It will look for messages such as temporary moderator or
         * administrator rights, but also usage of /modlogin.
         * 
         * @param Bot $pBot The bot which received the message.
         * @param string $sMessage The actual admin message.
         * @param array $aChunks The complete message split by spaces.
         */
        private function handleAdminMessage (Bot $pBot, $sMessage, $aChunks)
        {
            /**
             * A crew member logged into their account using /modlogin.
             * 
             * Gamemode:
             * "%s (Id:%d) has logged in as %s (%s) using /modlogin."
             * <Nuwani> *** aok (Id:7) has logged in as cake (administrator) using /modlogin.
             * <Nuwani> 05*** soktaart (Id:13) has logged in as cake (administrator) using /modlogin.
             */
            if (isset ($aChunks [10]) && $aChunks [4] == 'logged' && $aChunks [10] == '/modlogin.')
            {
                if ($aChunks[8] == '(manager)') {
                    $nLevel = LVP::LEVEL_MANAGEMENT;
                }
                else if ($aChunks [8] == '(administrator)') {
                    $nLevel = LVP :: LEVEL_ADMINISTRATOR;
                }
                else {
                    $nLevel = LVP :: LEVEL_MODERATOR;
                }
                
                $this -> m_pModule ['Crew'] -> addModLogin ($aChunks [1], $aChunks [7], $nLevel);
                
                // And done.
                return ;
            }
            
            /**
             * A crew member granted some extra rights to a person ingame.
             * Giving temp rights using a command from IRC returns the same
             * output as when somebody does the same thing ingame. So that's
             * processed in here as well.
             *
             * From IRC:
             * "%s (IRC) has granted temp. mod rights to %s (Id:%d)."
             * "%s (IRC) has granted temp. admin rights to %s (Id:%d)."
             * 
             * Gamemode:
             * "%s (Id:%d) has granted temp. mod rights to %s (Id:%d)."
             * "%s (Id:%d) has granted temp. admin rights to %s (Id:%d)."
             *
             * Unescaped regex:
             * ^([^ ]+) \((?:I[Dd]:\d+|IRC)\) has granted temp\.? ([^ ]+) rights to ([^ ]+) \(I[Dd]:\d+\)\.?$
             */
            if (preg_match('/^([^ ]+) \\((?:I[Dd]:\\d+|IRC)\\) has granted temp\\.? ([^ ]+) rights to ([^ ]+) \\(I[Dd]:\\d+\\)\\.?$/', $sMessage, $matches) > 0) {
                switch ($matches[2]) {
                    case 'mod':
                        $this->m_pModule['Crew']->addTempMod($matches[3], $matches[1]);
                        return;

                    case 'admin':
                        $this->m_pModule['Crew']->addTempAdmin($matches[3], $matches[1]);
                        return;
                }
            }

            /**
             * Somebody took some rights from a person ingame. Taking temp
             * rights using a command from IRC returns the same output as
             * when somebody does the same thing ingame. So that's processed
             * in here as well.
             *
             * From IRC:
             * "%s (IRC) has taken mod rights from %s (Id:%d)."
             * "%s (IRC) has taken admin rights from %s (Id:%d)."
             * 
             * Gamemode:
             * "%s (Id:%d) has taken mod rights from %s (Id:%d)."
             * "%s (Id:%d) has taken their own mod rights."
             * "%s (Id:%d) has taken admin rights from %s (Id:%d)."
             * "%s (Id:%d) has taken their own admin rights."
             *
             * Unescaped regex:
             * ^([^ ]+) \((?:I[Dd]:\d+|IRC)\) has taken (?:their own |)([^ ]+) rights(?: from ([^ ]+) \(I[Dd]:\d+\)|)\.?$
             */
            if (preg_match('/^([^ ]+) \\((?:I[Dd]:\\d+|IRC)\\) has taken (?:their own |)([^ ]+) rights(?: from ([^ ]+) \\(I[Dd]:\\d+\\)|)\\.?$/', $sMessage, $matches) > 0) {
                switch ($matches[2]) {
                    case 'mod':
                        $this->m_pModule['Crew']->removeMod(isset($matches[3]) ? $matches[3] : $matches[1]);
                        return;

                    case 'admin':
                        $this->m_pModule['Crew']->removeAdmin(isset($matches[3]) ? $matches[3] : $matches[1]);
                        return;
                }
            }
        }
        
        /**
         * I want the bot to able to respond to commands issued from the ingame
         * crew chat as well. We'll do exactly that, in here.
         * 
         * @param Bot $pBot The bot which received the message.
         * @param string $sNickname The nickname of the person speaking in the crew chat.
         * @param string $sMessage The message the person said.
         * @param array $aChunks The message split by spaces.
         */
        private function handleCrewChat (Bot $pBot, $sNickname, $sMessage, $aChunks)
        {
                $nId = (int) substr ($aChunks [3], 4, -3);
                
                if ($nId == 255 || $this -> m_pModule ['Crew'] -> isIngameCrew ($sNickname))
                {
                        $aChatChunks = array_slice ($aChunks, 4);
                        $sTrigger    = array_shift ($aChatChunks);
                        
                        if ($sTrigger [0] == '!')
                        {
                                if ($nId != 255)
                                {
                                        $nLevel = $this -> m_pModule ['Crew'] -> getLevel ($sNickname);
                                }
                                else
                                {
                                        $nLevel = LVP :: LEVEL_ADMINISTRATOR;
                                }
                                
                                $this -> m_pModule ['Commands'] -> handleCrewChat ($nLevel, $sNickname, $sTrigger, $aChatChunks);
                        }
                }
                else
                {
                        /** The person talking is not crew, maybe a report? **/
                        if (!$this -> m_pModule ['Players'] -> isCrewIngame ())
                        {
                                /** There's no crew ingame either, this must be a report then. **/
                                if ($this -> m_pModule -> config -> getDirective ('parser', 'reports'))
                                {
                                        $this -> m_pModule -> privmsg ($pBot, LVP :: CREW_CHANNEL,
                                                ModuleBase::COLOUR_ORANGE . '* Report by ' . $sNickname . ' (ID:' . $nId . ')'
                                                . ModuleBase::CLEAR . ': ' . $sMessage);
                                }
                        }
                }
        }
        
        /**
         * This method will be called as soon as the LVP gamemode is restarted.
         * This means that everything that was going on in the server, is no
         * longer going on. So, we need to clean things up.
         */
        private function handleGamemodeInit ()
        {
                $this -> m_pModule -> info (null, LVP :: CREW_CHANNEL, 'Everything reset due to gamemode restart.');
                
                $this -> m_pModule ['Crew'] -> clearIngameCrew ();
                $this -> m_pModule ['Players'] -> clearPlayers ();
        }
        
        /**
         * This method handles the IP address message from Nuwani. It will store
         * the IP and nickname combination together with a date into the
         * database for even less privacy.. err.. more stats.
         * 
         * @param Bot $pBot The bot which received the message.
         * @param integer $nId The ID of the player.
         * @param string $sNickname The person the IP belongs to.
         * @param string $sIp The IP in dotted format.
         * @param array $aChunks The original message in chunks.
         */
        private function handleIpMessage (Bot $pBot, $nId, $sNickname, $sIp, $aChunks)
        {
                $this -> m_pModule ['Players'] -> setPlayerKey ($nId, 'IP', $sIp);
                
                if (defined ('DEBUG_MODE') && DEBUG_MODE)
                {
                        // Don't save IPs when in debug mode.
                        return ;
                }
                
                $bResult = $this -> m_pModule ['IP'] -> insertIp ($sNickname, $sIp);
                if (!$bResult)
                {
                        $this -> m_pModule -> error ($pBot, LVP :: DEBUG_CHANNEL, 'Could not save IP address "' . $sIp . '": Trying again with a new connection...');
                        LVPDatabase :: restart ();
                        
                        $bResult = $this -> m_pModule ['IP'] -> insertIp ($sNickname, $sIp);
                        if ($bResult)
                        {
                                $this -> m_pModule -> success ($pBot, LVP :: DEBUG_CHANNEL, 'Saved IP address "' . $sIp . '".');
                        }
                        else
                        {
                                $this -> m_pModule -> error ($pBot, LVP :: DEBUG_CHANNEL, 'Could not save IP address "' . $sIp . '" with nickname "' . $sNickname . '".');
                        }
                }
        }
        
        /**
         * This method gets called as soon someone joins the LVP server. We'll
         * create an LVPPlayer object here for him/her and store the time he/she
         * joined.
         * 
         * @param Bot $pBot The bot which received the message.
         * @param integer $nId The ID of the player.
         * @param string $sNickname The person who joined the game.
         * @param array $aChunks The original message in chunks.
         */
        private function handleJoinGame (Bot $pBot, $nId, $sNickname, $aChunks)
        {
                $this -> m_pModule ['Players'] -> setPlayerKey ($nId, 'Nickname', $sNickname);
                $this -> m_pModule ['Players'] -> setPlayerKey ($nId, 'JoinTime', time ());
        }
        
        /**
         * This method gets called when someone leaves the server. We use this
         * to clean some stuff up. We also save the session time if this player
         * was registered.
         * 
         * @param Bot $pBot The bot which received the message.
         * @param integer $nId The ID of the player.
         * @param string $sNickname The person who left the server.
         * @param string $sReason The reason the connection was closed.
         * @param array $aChunks The original message in chunks.
         */
        private function handleLeaveGame (Bot $pBot, $nId, $sNickname, $sReason, $aChunks)
        {
                $nProfileId = $this -> m_pModule ['Players'] -> getPlayerKey ($nId, 'ProfileID');
                if ($nProfileId > 0)
                {
                        /** Save session time for this registered player. **/
                        $nJoinTime = $this -> m_pModule ['Players'] -> getPlayerKey ($nId, 'JoinTime');
                        $nSessionTime = time () - $nJoinTime;
                        
                        $db = LVPDatabase :: getInstance ();
                        $db -> query (
                                'INSERT INTO samp_ingame_test
                                        (user_id, part_time, session_time)
                                VALUES
                                        (' . $nProfileId . ', NOW(), ' . $nSessionTime . ')');
                }
                
                $this -> m_pModule ['Crew'] -> removeMod ($sNickname);
                $this -> m_pModule ['Crew'] -> removeAdmin ($sNickname);
                $this -> m_pModule ['Players'] -> deletePlayer ($nId);
        }
        
        /**
         * Registered players have the ability to log into their accounts in the
         * LVP server. We store the time they did that so we can see whether
         * they're registered or not. Also because we now know for sure that
         * this player is registered, we'll fetch the profile ID from the
         * database. This is used later on when the player leaves the game, to
         * store his/her session time.
         * 
         * @param Bot $pBot The bot which received the message.
         * @param integer $nId The ID of the player.
         * @param string $sNickname The person who logged in.
         * @param array $aChunks The original message in chunks.
         */
        private function handleLoggedIn (Bot $pBot, $nId, $sNickname, $aChunks)
        {
                $this -> m_pModule ['Players'] -> setPlayerKey ($nId, 'LogInTime', time ());
                
                $nLevel = $this -> m_pModule ['Crew'] -> getLevel ($sNickname);
                $this -> m_pModule ['Players'] -> setPlayerKey ($nId, 'Level', $nLevel);
                
                $db = LVPDatabase :: getInstance ();
                $pResult = $db -> query (
                        'SELECT u.user_id
                        FROM lvp_mainserver.users_nickname n
                        LEFT JOIN lvp_mainserver.users u ON u.user_id = n.user_id
                        LEFT JOIN lvp_mainserver.users_mutable m ON m.user_id = u.user_id
                        WHERE n.nickname = "' . $db -> real_escape_string ($sNickname) . '"');
                
                if ($pResult !== false && $pResult -> num_rows > 0)
                {
                        list ($nProfileId) = $pResult -> fetch_row ();
                        $this -> m_pModule -> players [$nId] -> fetchInformation ($nProfileId);
                        $pResult -> free ();
                }
                
                if ($this -> m_pModule -> config -> getDirective ('parser', 'welcomemsg'))
                {
                        $this -> m_pModule -> welcomemsg -> handleWelcomeMessage ($nId, $sNickname, $aChunks);
                }
        }
        
        /**
         * If there's a command in the main chat message, this method will be
         * the one responsible so that the command handler knows about it.
         * 
         * @param Bot $pBot The bot which received the message.
         * @param integer $nId The ID of the player.
         * @param string $sNickname The nickname of the person saying something.
         * @param string $sMessage The message of the player.
         * @param array $aChunks The original message in chunks.
         */
        private function handleMainChatMessage (Bot $pBot, $nId, $sNickname, $sMessage, $aChunks)
        {
                list ($sTrigger, $sParams, $aParams) = Util :: parseMessage ($sMessage);
                $this -> m_pModule ['Commands'] -> handleMainChat (LVP :: LEVEL_NONE, $sNickname, $sTrigger, $aParams);
                
                //$this -> m_pModule -> privmsg ($pBot, LVP :: DEBUG_CHANNEL, '[' . $nId . '] <' . $sNickname . '> ' . $sMessage);
        }
}
?>
