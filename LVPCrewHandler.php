<?php
/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPCrewHandler extends LVPEchoHandlerClass
{
        const STATE_FILE = 'Data/LVP/IngameCrew.dat';
        
        /**
         * Because I don't want to hardcode all the colors for the different
         * levels on LVP, this array will keep track of all of them. Using some
         * methods elsewhere in this class, these can be put to good use for
         * consistent and professional results.
         * 
         * @var array
         */
        private $m_aColors;
        
        /**
         * We'll store the managers in here.
         * 
         * @var array
         */
        private $m_aManagement;
        
        /**
         * The administrators are in this array.
         * 
         * @var array
         */
        private $m_aAdministrator;
        
        /**
         * Although very little used as most developers get administrator rights
         * or are administrators to begin with, those who don't have
         * administrator rights yet are stored in here.
         * 
         * @var array
         */
        private $m_aDeveloper;
        
        /**
         * One of the main features is keeping track of the crew members who
         * are ingame under a different name. We'll do that using this array.
         * The name used as current ingame nickname is the key, the actual crew
         * member's name is the value.
         * 
         * @var array
         */
        private $m_aModLogin;
        
        /**
         * The same for mod rights applies for admin rights as well. Same
         * structure of the array in here too.
         * 
         * @var array
         */
        private $m_aTempAdmin;
        
        /**
         * The constructor will call the parent constructor and prepare some
         * extra stuff in this class as an added bonus. This includes the level
         * colors and initializing the ingamecrew variables.
         * 
         * @param LVPEchoHandler $pEchoHandler The LVPEchoHandler module we're residing in.
         * @param array $aCrewColors The colors for every level within the crew.
         */
        public function __construct(LVPEchoHandler $pEchoHandler, $aCrewColors) {
                parent::__construct($pEchoHandler);
                
                $this->m_aColors = $aCrewColors;
                
                $this->clearIngameCrew ();
                $this->registerCommands ();
                $this->loadState();
        }

        /**
         * The destructor will save the state of all the ingame cre when the
         * bot is shutting down. This way, we can restore them all when we
         * return back in service.
         */
        public function __destruct() {
                $this->saveState();
        }

        public function loadState() {
            if (file_exists(self::STATE_FILE)) {
                $data = unserialize(file_get_contents(self::STATE_FILE));
                $this->m_aModLogin = $data[0];
                $this->m_aTempAdmin = $data[2];
                
                unlink (self::STATE_FILE);

                return true;
            }

            return false;
        }

        public function saveState() {
            file_put_contents (self::STATE_FILE, serialize (array(
                $this->m_aModLogin,
                [], // Backwards compatibility, temp mods were here previously
                $this->m_aTempAdmin
            )));
        }
        
        /**
         * This method will register the commands this class handles to the
         * LVPCommandHandler.
         */
        private function registerCommands() {
                $aCommands = array(
                        '!ingamecrew' => LVP::LEVEL_NONE,
                        '!updatecrew' => LVP::LEVEL_ADMINISTRATOR
                );
                
                foreach ($aCommands as $sTrigger => $nLevel) {
                        $this->m_pModule['Commands']->register(new LVPCommand($sTrigger, $nLevel, array($this, 'handleCommands')));
                }
        }
        
        /**
         * This method will be used to clean up all temporary crew as well as
         * crew logged in other a different name. This can be done forcibly but
         * preferably automatically when the gamemode is restarted.
         */
        public function clearIngameCrew() {
                $this->m_aModLogin = array();
                $this->m_aTempAdmin = array();
        }
        
        /**
         * This method will return the colorcode for the specified LVP level.
         * 
         * @param integer $nLevel The level we want the color from.
         * @return string
         */
        public function getLevelColor($nLevel) {
                if (isset($this->m_aColors[$nLevel])) {
                        return ModuleBase::COLOUR . $this->m_aColors[$nLevel];
                }
                
                return '';
        }
        
        /**
         * Because the level system used in this module differs quite a bit from
         * the one used everywhere else on LVP, we need a translator. Guess what?
         * This is it. It will take a gamemode level and spits out the same
         * level as represented in this module.
         * 
         * @param string $gamemodeLevel The level as used in the gamemode.
         * @param boolean $isDeveloper Whether the person is a developer.
         * @return integer The level representation we use throughout this module.
         */
        public static function translateGamemodeLevel ($gamemodeLevel, $isDeveloper)
        {
                switch ($gamemodeLevel)
                {
                        default:
                        case 'Player':
                        {
                                if ($isDeveloper) {
                                        return LVP :: LEVEL_DEVELOPER;
                                }
                                return LVP :: LEVEL_NONE;
                        }
                        
                        case 'Administrator':
                        {
                                return LVP :: LEVEL_ADMINISTRATOR;
                        }
                        
                        case 'Management':
                        {
                                return LVP :: LEVEL_MANAGEMENT;
                        }
                }
        }
        
        /**
         * This method will wrap the content with the colorcode of the specified
         * level, and close it again with the reset character.
         * 
         * @param integer $nLevel The level we want the color from.
         * @param string $sContent The string we want in this color.
         * @return string
         */
        public function wrapLevelColor ($nLevel, $sContent)
        {
                return $this -> getLevelColor ($nLevel) . $sContent . ModuleBase :: CLEAR;
        }
        
        /**
         * This method does the same as the wrapLevelColor () method, except
         * that it determines the level from the nickname given, and wraps that
         * very nickname with the right color.
         * 
         * @param string $sNickname The nickname we're wrapping.
         * @return string
         */
        public function wrapLevelColorName ($sNickname)
        {
                return $this -> getLevelColor ($this -> getLevel ($sNickname)) .
                        $sNickname . ModuleBase :: CLEAR;
        }
        
        /**
         * This method returns a nice string for the given level. This is
         * because we want to show a name for the level sometimes as well.
         * 
         * @param integer $nLevel The level we want to make readable.
         * @return string
         */
        public function getLevelName ($nLevel, $bLowercase = true)
        {
                switch ($nLevel)
                {
                        case LVP :: LEVEL_MANAGEMENT:    { $sReturn = 'Management';    break; }
                        case LVP :: LEVEL_ADMINISTRATOR: { $sReturn = 'Administrator'; break; }
                        case LVP :: LEVEL_DEVELOPER:     { $sReturn = 'Developer';     break; }
                }
                
                if ($bLowercase)
                {
                        $sReturn = strtolower ($sReturn);
                }
                
                return $sReturn;
        }
        
        /**
         * This method will get the ingame level of the given nickname.
         * 
         * @param string $sNickname The case sensitive nickname we're looking for.
         * @return integer
         */
        public function getLevel ($sNickname)
        {
                $aLevels = array
                (
                        LVP :: LEVEL_MANAGEMENT,
                        LVP :: LEVEL_ADMINISTRATOR,
                        LVP :: LEVEL_DEVELOPER,
                );
                
                foreach ($aLevels as $nLevel)
                {
                        if ($this -> checkLevel ($sNickname, $nLevel))
                        {
                                return $nLevel;
                        }
                }
                
                return LVP :: LEVEL_NONE;
        }
        
        /**
         * Checks whether the given nickname has the supplied level. If not, it
         * returns false, however, true when the person has been blessed with
         * the rights.
         * 
         * @param string $sNickname The nickname to check.
         * @param integer $nLevel The level to check for.
         * @return boolean
         */
        public function checkLevel ($sNickname, $nLevel)
        {
                switch ($nLevel)
                {
                        case LVP :: LEVEL_MANAGEMENT:    { $aCrew = & $this -> m_aManagement;     break; }
                        case LVP :: LEVEL_ADMINISTRATOR: { $aCrew = & $this -> m_aAdministrator;  break; }
                        case LVP :: LEVEL_DEVELOPER:     { $aCrew = & $this -> m_aDeveloper;      break; }
                }
                
                $sLowerNickname = strtolower ($sNickname);
                
                return (isset ($aCrew [$sLowerNickname]) && $aCrew [$sLowerNickname] ['Nickname'] == $sNickname);
        }
        
        /**
         * This method will check if the given nickname is crew ingame. This not
         * only includes the normal admins and mods, but also the temporary crew
         * as well as undercover crew.
         * 
         * @param string $nickname The nickname to check whether he/she is crew.
         * @return boolean
         */
        public function isIngameCrew($nickname) {
                return $this->isPermanentCrew($nickname) ||
                       isset ($this->m_aModLogin[$nickname]) ||
                       isset ($this->m_aTempAdmin[$nickname]);
        }
        
        /**
         * This method return true when the given nickname is part of the
         * permanent crew.
         * 
         * @param string $nickname The nickname to check.
         * @return boolean
         */
        public function isPermanentCrew($nickname) {
                return $this->checkLevel($nickname, LVP::LEVEL_MANAGEMENT) ||
                       $this->checkLevel($nickname, LVP::LEVEL_ADMINISTRATOR) ||
                       $this->checkLevel($nickname, LVP::LEVEL_DEVELOPER);
        }
        
        /**
         * This method deals with processing the command input from IRC. It
         * determines what needs to be done according to the trigger and the
         * given parameters. It then uses the methods in this class to gather
         * the requested information and outputs a message understandable for
         * human beings with the information they need.
         * 
         * @param LVPEchoHandler $pModule A pointer back to the main module.
         * @param integer $nMode The mode the command is executing in.
         * @param integer $nLevel The level we're operating at.
         * @param string $sChannel The channel we'll be sending our output to.
         * @param string $sNickname The nickname who triggered the command.
         * @param string $sTrigger The trigger that set this in motion.
         * @param string $sParams All of the text following the trigger.
         * @param array $aParams Same as above, except split into an array.
         * @return integer
         */
        public function handleCommands($pModule, $nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams) {
                switch ($sTrigger) {
                        case '!ingamecrew':
                                return $this->handleIngameCrew($nLevel);

                        case '!updatecrew':
                                return $this->handleUpdateCrew();
                }
        }
        
        /**
         * This method cleans all the references of someone being either
         * /modlogin'ed or a temp admin. If the second parameter is set to true,
         * and they didn't have temporary rights or /modlogin, a message will
         * be sent to the crew channel indicating that the given nickname has
         * lost their rights.
         * 
         * @param string $sNickname The nickname of the person who's now without admin rights.
         * @param boolean $bFromCommand Whether it was the result of a command or just quiting.
         */
        public function removeAdmin($sNickname, $bFromCommand = false) {
                $bRemoved = false;
                $bRemoved |= $this->removeModLogin($sNickname, $bFromCommand);
                $bRemoved |= $this->removeTempAdmin($sNickname, $bFromCommand);
                
                if ($bFromCommand) {
                        $sNickname = $this->wrapLevelColorName($sNickname);
                        $this->m_pModule->info(null, LVP::CREW_CHANNEL, $sNickname . ' is no longer an administrator.');
                }
        }
        
        /**
         * This method will register a name as being ingame with administrator
         * rights.
         * 
         * @param string $sFakeName The name under which the crew member is currently ingame.
         * @param string $sRealName The name we all know the crew member under.
         * @param integer $nLevel The level of the crew member.
         */
        public function addModLogin($sFakeName, $sRealName, $nLevel) {
                if ($sFakeName == $sRealName) {
                        // Yeah, whatever.
                        return;
                }
                
                $this->m_aModLogin[$sFakeName] = $sRealName;
                
                $sMessage = ModuleBase::BOLD . $sFakeName . ModuleBase::BOLD .
                        ' has logged in as ' . $this -> wrapLevelColorName ($sRealName);
                
                $this->m_pModule->info(null, LVP::CREW_CHANNEL, $sMessage . '.');
                
                $pPlayer = $this->m_pModule['Players']->getPlayerByName($sFakeName);
                if ($pPlayer != null) {
                        // Update their profile ID, so that their session time can be recorded in the crew monitor.
                        $db = LVPDatabase::getInstance();
                        $pResult = $db->query(
                                'SELECT u.user_id
                                FROM lvp_mainserver.users_nickname n
                                LEFT JOIN lvp_mainserver.users u ON u.user_id = n.user_id
                                LEFT JOIN lvp_mainserver.users_mutable m ON m.user_id = u.user_id
                                WHERE n.nickname = "' . $db->real_escape_string($sRealName) . '"');
                        
                        if ($pResult !== false && $pResult->num_rows > 0) {
                                list ($nProfileId) = $pResult->fetch_row();
                                
                                $pPlayer->fetchInformation($nProfileId);
                                
                                $pPlayer['TempLevel'] = $pPlayer['Level'];
                                $pPlayer['Level'] = 0;
                                
                                $pResult->free();
                        }
                }
        }
        
        /**
         * This method will simply delete the entry of the given name as being
         * undercover ingame. Returns a boolean indicating whether we removed
         * someone.
         * 
         * @param string $sFakeName The name that needs to be removed.
         * @param boolean $bSilent When true, a message will not be sent to the crew channel.
         * @return boolean
         */
        public function removeModLogin($sFakeName, $bSilent = false) {
                if (!isset($this->m_aModLogin[$sFakeName])) {
                        return false;
                }

                $sRealName = $this->m_aModLogin[$sFakeName];

                unset($this->m_aModLogin[$sFakeName]);
                
                $pPlayer = $this->m_pModule['Players']->getPlayerByName($sFakeName);
                if ($pPlayer != null) {
                        $pPlayer['TempLevel'] = LVP::LEVEL_NONE;
                }
                
                if (!$bSilent) {
                        $sRealName = $this->wrapLevelColorName($sRealName);
                        $this->m_pModule->info(null, LVP::CREW_CHANNEL, ModuleBase::BOLD . $sFakeName .
                                ModuleBase::BOLD . ' (' . $sRealName . ') is no longer ingame.');
                }
                
                return true;
        }
        
        /**
         * This method adds the specified name as a temporary ingame
         * administrator to the internal array.
         * 
         * @param string $sTempAdminName The name of the person who was given temp admin rights.
         * @param string $sCrewMember The crew member who gave this person the rights.
         */
        public function addTempAdmin($sTempAdminName, $sCrewMember) {
                $this->m_aTempAdmin[$sTempAdminName] = $sCrewMember;
                
                $sCrewMember = $this->wrapLevelColorName($sCrewMember);
                $sMessage = ModuleBase::BOLD . $sTempAdminName . ModuleBase::BOLD .
                        ' has been given temporary administrator rights by ' . $sCrewMember;
                
                $this->m_pModule->info(null, LVP::CREW_CHANNEL, $sMessage . '.');
                
                $pPlayer = $this->m_pModule['Players']->getPlayerByName($sTempAdminName);
                if ($pPlayer != null) {
                        $pPlayer['TempLevel'] = LVP::LEVEL_ADMINISTRATOR;
                }
        }
        
        /**
         * This method will remove the supplied name as being given admin
         * rights. It will also check if there's a crew member logged in under
         * a different name, who's got their rights taken away. If so, we remove
         * them as being logged in undercover as well.
         * 
         * @param string $sTempAdminName The name who's got their rights taken away.
         * @param boolean $bSilent When true, a message will not be sent to the crew channel.
         * @return boolean
         */
        public function removeTempAdmin($sTempAdminName, $bSilent = false) {
                if (!isset($this->m_aTempAdmin[$sTempAdminName])) {
                        return false;
                }

                unset($this->m_aTempAdmin[$sTempAdminName]);
                
                $pPlayer = $this->m_pModule['Players']->getPlayerByName($sTempAdminName);
                if ($pPlayer != null) {
                        $pPlayer['TempLevel'] = LVP::LEVEL_NONE;
                }
                
                if (!$bSilent) {
                        $sMessage = ModuleBase::BOLD . $sTempAdminName . ModuleBase::BOLD . ' is no longer ingame.';
                        $this->m_pModule->info(null, LVP::CREW_CHANNEL, $sMessage);
                }
                
                return true;
        }
        
        /**
         * This method will report the ingame crew at this moment. It uses the
         * QuerySAMPServer class to query the LVP server and then check for
         * ingame crew. It returns a nicely formatted message. This command is
         * available for all LVP channels, however, the temporary crew line is
         * only available in crew and management channels.
         * 
         * @param integer $nLevel The level we're operating at.
         * @return string
         */
        private function handleIngameCrew($nLevel) {
                if ($nLevel >= LVP::LEVEL_ADMINISTRATOR) {
                        // Temporary crew.
                        echo ModuleBase::COLOUR_TEAL . '* Temp admins' . ModuleBase::CLEAR . ': ';
                        if (count($this->m_aTempAdmin) == 0) {
                                echo ModuleBase :: COLOUR_DARKGREY . 'None ';
                        }
                        else foreach ($this->m_aTempAdmin as $sNickname => $sCrewMember) {
                                echo $sNickname . ' (by ' . $sCrewMember . ') ';
                        }
                        
                        echo ModuleBase::COLOUR_TEAL . '* Undercover' . ModuleBase::CLEAR . ': ';
                        if (count ($this->m_aModLogin) == 0) {
                                echo ModuleBase :: COLOUR_DARKGREY . 'None ';
                        }
                        else foreach ($this->m_aModLogin as $sNickname => $sRealname) {
                                echo $sRealname . ' (as ' . $sNickname . ') ';
                        }
                        
                        echo PHP_EOL;
                }
                
                // Permanent crew.
                $aIngameCrew = array();
                foreach ($this->m_pModule->players as $pPlayer) {
                        if ($pPlayer['Level'] >= LVP::LEVEL_ADMINISTRATOR) {
                                $aIngameCrew[] = $pPlayer;
                        }
                }
                
                $nPlayers = count($this->m_pModule->players);
                if (count($aIngameCrew) == 0) {
                        echo 'No crew ingame with ' . $nPlayers . ' player' .
                                ($nPlayers == 1 ? '' : 's') . ' ingame.';
                } else {
                        echo ModuleBase::COLOUR_TEAL . '* Ingame crew' . ModuleBase::CLEAR .
                                ' (' . count($aIngameCrew) . '/' . $nPlayers . '): ';
                        
                        $bFirst = true;
                        foreach ($aIngameCrew as $pPlayer) {
                                if ($bFirst) {
                                        $bFirst = false;
                                } else {
                                        echo ', ';
                                }
                                
                                $sNickname = $pPlayer['Nickname'];
                                
                                $nLevel = $this->getLevel($sNickname);

                                echo $this->wrapLevelColor($nLevel, $sNickname);
                                echo ' (ID: ' . $pPlayer ['ID'] . ')';
                        }
                }
                
                return LVPCommand::OUTPUT_NORMAL;
        }
        
        /**
         * This method updates the internal array with all the crew members.
         * After a successful update, an array with the number of members of
         * each rank is returned.
         */
        public function handleUpdateCrew() {
                $db = LVPDatabase::getInstance();
                $pResult = $db->query(
                        'SELECT u.user_id, u.username, u.level, u.is_developer
                        FROM lvp_mainserver.users u
                        LEFT JOIN lvp_website.users_links l ON l.samp_id = u.user_id
                        LEFT JOIN lvp_website.website_settings s ON s.user_id = l.user_id
                        WHERE u.level != "Player" OR u.is_developer <> 0
                        ORDER BY u.level DESC, u.username ASC');
                
                if ($pResult === false || $pResult -> num_rows == 0) {
                        echo 'Could not fetch crew information from database.';
                        return LVPCommand::OUTPUT_ERROR;
                }
                
                $this->m_aManagement = array();
                $this->m_aAdministrator = array();
                $this->m_aDeveloper = array();
                
                while ($aRow = $pResult -> fetch_assoc()) {
                        $aRow['level'] = self::translateGamemodeLevel($aRow['level'], $aRow['is_developer']);
                        
                        switch ($aRow['level']) {
                                case LVP::LEVEL_MANAGEMENT:
                                        $aCrewMembers = &$this->m_aManagement;
                                        break;

                                case LVP::LEVEL_ADMINISTRATOR:
                                        $aCrewMembers = &$this->m_aAdministrator;
                                        break;

                                case LVP::LEVEL_DEVELOPER:
                                        $aCrewMembers = &$this->m_aDeveloper;
                                        break;

                                default:
                                        continue;
                        }
                        
                        $aCrewMembers[strtolower($aRow['username'])] = array(
                                'ProfileID' => $aRow['user_id'],
                                'Nickname' => $aRow['username']
                        );
                }
                
                $aCounts = array(
                        LVP::LEVEL_MANAGEMENT => count($this->m_aManagement),
                        LVP::LEVEL_ADMINISTRATOR => count($this->m_aAdministrator),
                        LVP::LEVEL_DEVELOPER => count($this->m_aDeveloper)
                );
                
                $sMessage = 'Crew updated. ';
                foreach ($aCounts as $nLevel => $nCount) {
                        $sMessage .= $nCount . ' ' . $this->wrapLevelColor($nLevel, $this->getLevelName($nLevel)) . ', ';
                }
                
                echo substr ($sMessage, 0, -2);
                return LVPCommand::OUTPUT_SUCCESS;
        }

        /** 
         * This method is used to check whether temporary crew or undercover
         * crew is still ingame. If not, they are unregistered as being ingame
         * as temporary crew or undercover.
         */
        public function syncPlayers() {
                $playerManager = $this->m_pModule->players;

                $tempCrew = $this->m_aTempAdmin;
                foreach ($tempCrew as $sNickname => $sCrewMember) {
                        if ($playerManager->getPlayerByName($sNickname) === null) {
                                unset($this->m_aTempAdmin[$sNickname]);
                        }
                }

                $undercoverCrew = $this->m_aModLogin;
                foreach ($undercoverCrew as $sNickname => $sRealname) {
                        if ($playerManager->getPlayerByName($sNickname) === null) {
                                unset($this->m_aModLogin[$sNickname]);
                        }
                }

                return true;
        }
}
