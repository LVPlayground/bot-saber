<?php
/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPIpManager extends LVPEchoHandlerClass {
        
        /**
         * These constants are used to differentiate between nicknames and IPs
         * in the command handler of this class.
         * 
         * @var integer
         */
        const TYPE_IP = 1;
        const TYPE_RANGED_IP = 2;
        const TYPE_NICKNAME = 3;
        
        /**
         * The constructor has been overridden so that we can register our
         * commands with the command handler as soon as we fire up.
         * 
         * @param LVPEchoHandler $pModule The LVPEchoHandler module we're residing in.
         */
        public function __construct(LVPEchoHandler $pModule) {
                parent::__construct($pModule);
                
                $this->registerCommands();
        }
        
        /**
         * This method will register all the commands handled by this class to
         * the LVPCommandHandler.
         */
        public function registerCommands() {
                $aCommands = array(
                        '!ipinfo'               => LVP::LEVEL_ADMINISTRATOR,
                        '!ipinfoname'           => LVP::LEVEL_ADMINISTRATOR,
                        
                        '!iplocation'           => LVP::LEVEL_ADMINISTRATOR,
                        '!iploc'                => LVP::LEVEL_ADMINISTRATOR,
                        '!namelocation'         => LVP::LEVEL_ADMINISTRATOR,
                        '!nameloc'              => LVP::LEVEL_ADMINISTRATOR,
                        '!idlocation'           => LVP::LEVEL_ADMINISTRATOR,
                        '!idloc'                => LVP::LEVEL_ADMINISTRATOR,
                        
                        '!hidename'             => LVP::LEVEL_MANAGEMENT,
                        '!hideip'               => LVP::LEVEL_MANAGEMENT
                );
                
                $pCommandHandler = $this->m_pModule['Commands'];
                foreach ($aCommands as $sTrigger => $nLevel) {
                        $pCommandHandler->register(new LVPCommand(
                                $sTrigger,
                                $nLevel,
                                array ($this, 'handleCommands')
                        ));
                }
        }
        
        /**
         * This method provides us with a work around so that we always get a
         * signed integer.
         * 
         * @param string $ip The IP address in dotted format.
         * @return integer
         */
        public function ip2long($ip) {
                list(, $longIp) = unpack('l', pack('l', ip2long($ip)));
                return $longIp;
        }
        
        /**
         * This method will simply insert the supplied IP with the given
         * nickname into the IP database. Returns a boolean indicating it did
         * that successfully or not.
         * 
         * @param string $nickname The nickname belonging to the IP.
         * @param string $ip The IP address in dotted notation.
         * @return boolean
         */
        public function insertIp($nickname, $ip) {
                $longIp = $this->ip2long($ip);
                
                $statement = LVPDatabase::getInstance()->prepare(
                        'INSERT INTO samp_addresses (
                                user_id, join_date, nickname, ip_address
                        ) VALUES (
                                0, NOW(), ?, ?
                        )'
                );

                if ($statement === false) {
			$this->m_pModule->error($pBot, LVP::DEBUG_CHANNEL,
                                'Error: ' . LVPDatabase::getInstance()->error);
                        return false;
                }
                
                $statement->bind_param('si', $nickname, $longIp);
                
                return $statement->execute();
        }
        
        /**
         * This method returns true when the given ID is numeric and between 0
         * and 199. These are valid IDs within SA-MP.
         * 
         * @param integer $id The ID to check for validness.
         * @return boolean
         */
        public function isValidId($id) {
                return is_numeric($id) && $id >= 0 && $id <= 199;
        }
        
        /**
         * This method checks if the given input string is a valid IP statement.
         * What's so special about this method however, is that it incorporates
         * ranged inputs, like 255.255.*.*, but *.*.*.* is not accepted. It also
         * checks if every octet is between 0 and 255, if it's not a wildcard.
         * 
         * @param string $ip The input string we want to check.
         * @return boolean
         */
        public function isRangedIp($ip) {
                $matches = array ();
                if (preg_match('/^(\d{1,3})\.(\d{1,3}|\*)\.(\d{1,3}|\*)\.\*$/', $ip, $matches) == 0) {
                        return false;
                }
                unset($matches[0]);
                
                foreach ($matches as $octet) {
                        if ($octet == '*') {
                                continue;
                        }
                        
                        if ($octet < 0 || $octet > 255) {
                                return false;
                        }
                }
                
                return true;
        }
        
        /**
         * This method checks if the given string is a valid IP. Wildcards are
         * not considered valid by this method.
         * 
         * @param string $ip The input string to check.
         * @return boolean
         */
        public function isValidIp($ip) {
                return ip2long($ip) !== false;
        }
        
        /**
         * This method will simply check if the given IP address is an IPv6
         * address or not.
         * 
         * @param string $ip The IP string to check.
         * @return boolean
         */
        public function isValidIpV6($ip) {
                return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }
        
        /**
         * This method deals with processing the command input from IRC. It
         * determines what needs to be done according to the trigger and the
         * given parameters. It then uses the methods in this class to gather
         * the requested information and outputs a message understandable for
         * human beings with the information they need.
         * 
         * @param LVPEchoHandler $mainModule A pointer back to the main module.
         * @param integer $mode The mode the command is executing in.
         * @param integer $level The level we're operating at.
         * @param string $channel The channel we'll be sending our output to.
         * @param string $nickname The nickname who triggered the command.
         * @param string $trigger The trigger that set this in motion.
         * @param string $params All of the text following the trigger.
         * @param array $splitParams Same as above, except split into an array.
         * @return string
         */
        public function handleCommands ($mainModule, $mode, $level, $channel, $nickname, $trigger, $params, $splitParams) {
                switch ($trigger) {
                        case '!ipinfo':
                        case '!ipinfoname':
                                return $this->handleIpInfo($level, $trigger, $params, $splitParams);
                        
                        case '!iplocation':
                        case '!iploc':
                                return $this->handleIpLocation($trigger, $params, $splitParams);
                        
                        case '!namelocation':
                        case '!nameloc':
                        case '!idlocation':
                        case '!idloc':
                                return $this->handleNameLocation($trigger, $params, $splitParams);
                        
                        case '!hideip':
                        case '!hidename':
                                return $this->handleHideFake($trigger, $params, $splitParams);
                        
                        default:
                                echo 'This command has not been implemented.';
                                return LVPCommand::OUTPUT_ERROR;
                }
        }
        
        /**
         * For the hypocrites on LVP, I've especially built a hiding feature for
         * both names as well as IPs. This method provides the code that will
         * allow the management to set a fake string that's shown instead of the
         * real information.
         * 
         * @param string $sTrigger The trigger that set this in motion.
         * @param string $sParams All of the text following the trigger.
         * @param array $aParams Same as above, except split into an array.
         * @return string
         */
        private function handleHideFake ($sTrigger, $sParams, $aParams)
        {
                if ($sParams == null || count ($aParams) == 0)
                {
                        echo $sTrigger . ' ';
                        switch ($sTrigger)
                        {
                                case '!hideip':   echo 'IP';   break ;
                                case '!hidename': echo 'Name'; break ;
                        }
                        echo ' [FakeMessage]';
                        
                        return LVPCommand :: OUTPUT_USAGE;
                }
                
                $sSubject     = Util::stripFormat(array_shift($aParams));
                $sOrigSubject = $sSubject;
                $sFakeString  = empty ($aParams) ? null : implode (' ', $aParams);
                
                if ($this -> isValidIpV6 ($sSubject))
                {
                        echo 'No IPv6 support.';
                        return LVPCommand :: OUTPUT_ERROR;
                }
                else if ($this -> isRangedIp ($sSubject))
                {
                        echo 'It\'s not possible to hide a complete range.';
                        return LVPCommand :: OUTPUT_ERROR;
                }
                
                if ($this -> isValidIp ($sSubject))
                {
                        $sTable = 'samp_address_hide_ip';
                        $sField = 'ip_address';
                        $sBindParam = 'i';
                        $sSubject = $this -> ip2long ($sSubject);
                }
                else
                {
                        $sTable = 'samp_address_hide_nickname';
                        $sField = 'nickname';
                        $sBindParam = 's';
                }
                
                $sQuery =
                        'SELECT
                                display_string
                        FROM
                                ' . $sTable . '
                        WHERE
                                ' . $sField . ' = ?';
                
                $pStatement = LVPDatabase :: getInstance () -> prepare ($sQuery);
                if ($pStatement === false)
                {
                        echo 'There seems to be a problem with the database. Try again later.';
                        return LVPCommand :: OUTPUT_ERROR;
                }
                $pStatement -> bind_param ($sBindParam, $sSubject);
                $pStatement -> bind_result ($sOldFakeString);
                
                if (!$pStatement -> execute ())
                {
                        echo 'Database error: ' . $pStatement -> error;
                        return LVPCommand :: OUTPUT_ERROR;
                }
                $pStatement -> close ();
                
                $sQuery =
                        'INSERT INTO ' . $sTable . ' (
                                ' . $sField . ', display_string
                        ) VALUES (
                                ?, ?
                        ) ON DUPLICATE KEY UPDATE display_string = ?';
                
                $pStatement = LVPDatabase :: getInstance () -> prepare ($sQuery);
                if ($pStatement === false)
                {
                        echo 'There seems to be a problem with the database. Try again later.';
                        return LVPCommand :: OUTPUT_ERROR;
                }
                $pStatement -> bind_param ($sBindParam . 'ss', $sSubject, $sFakeString, $sFakeString);
                
                if ($pStatement -> execute ())
                {
                        echo '"' . $sOrigSubject . '" is now hidden.';
                        return LVPCommand :: OUTPUT_SUCCESS;
                }
                else
                {
                        echo 'Database error: ' . $pStatement -> error;
                        return LVPCommand :: OUTPUT_ERROR;
                }
        }
        
        /**
         * This method will handle the !iplocation command. It takes an IP
         * address or hostname as a parameter, and will report the found
         * locations using the GeoIP database.
         * 
         * @param string $sTrigger The trigger that set this in motion.
         * @param string $sParams All of the text following the trigger.
         * @param array $aParams Same as above, except split into an array.
         * @return string
         */
        private function handleIpLocation ($sTrigger, $sParams, $aParams)
        {
                if ($sParams !== null) {
                        $sParams = Util::stripFormat($sParams);
                }

                if ($sParams === null || $sParams === '') {
                        echo $sTrigger . ' IP // ' . $sTrigger . ' Hostname';
                        return LVPCommand :: OUTPUT_USAGE;
                }
                
                if ($this -> isValidIpV6 ($sParams))
                {
                        echo 'No IPv6 support.';
                        return LVPCommand :: OUTPUT_ERROR;
                }
                else if ($this -> isRangedIp ($sParams))
                {
                        echo 'It\'s not possible to determine the location from a range.';
                        return LVPCommand :: OUTPUT_ERROR;
                }
                
                $pLocation = new Location ();
                
                if ($this -> isValidIp ($sParams))
                {
                        echo ModuleBase :: COLOUR_TEAL . '* ';
                        echo 'Location info for IP "' . $sParams . '"' . ModuleBase :: CLEAR . ': ';
                        $aInfo = $pLocation -> lookup ($sParams);
                        
                        $bFirst = true;
                        foreach ($aInfo as $sKey => $sValue)
                        {
                                if ($sKey == 'country_2') continue;
                                
                                if ($sValue != '--')
                                {
                                        if ($bFirst) $bFirst = false;
                                        else         echo ', ';
                                        echo ucfirst ($sKey) . ': ' . $sValue;
                                }
                        }
                        
                        return LVPCommand :: OUTPUT_NORMAL;
                }
                else
                {
                        // Hostname, resolve it and show the countries.
                        $aIps = gethostbynamel ($sParams);
                        if ($aIps === false)
                        {
                                echo 'Could not resolve the hostname.';
                                return LVPCommand :: OUTPUT_ERROR;
                        }
                        
                        echo ModuleBase :: COLOUR_TEAL . '* ';
                        echo 'Location info for hostname "' . $sParams . '"' . ModuleBase :: CLEAR . ': ';
                        
                        $bFirst = true;
                        foreach ($aIps as $sIp)
                        {
                                $aInfo = $pLocation -> lookup ($sIp);
                                
                                if ($bFirst) $bFirst = false;
                                else         echo ', ';
                                
                                echo $sIp . ' - ' . $aInfo ['country'];
                        }
                        
                        return LVPCommand :: OUTPUT_NORMAL;
                }
        }
        
        /**
         * The !namelocation command is handled in this method. It takes only a
         * nickname as a parameter and will lookup all IPs with this nickname
         * in the database. After that, all locations will be looked up and
         * processed for uniqueness.
         * 
         * @param string $sTrigger The trigger that set this in motion.
         * @param string $sParams All of the text following the trigger.
         * @param array $aParams Same as above, except split into an array.
         * @return string
         */
        private function handleNameLocation ($sTrigger, $sParams, $aParams)
        {
                if ($sParams !== null) {
                        $sParams = Util::stripFormat($sParams);
                }

                if ($sParams === null || $sParams === '') {
                        echo $sTrigger . ' Name // ' . $sTrigger . ' ID';
                        return LVPCommand :: OUTPUT_USAGE;
                }
                
                if ($this -> isValidId ($sParams))
                {
                        $pPlayer = $this -> m_pModule ['Players'] -> getPlayer ($sParams);
                        
                        if ($pPlayer == null)
                        {
                                echo 'ID not found.';
                                return LVPCommand :: OUTPUT_ERROR;
                        }
                        
                        $sParams = $pPlayer ['Nickname'];
                }
                
                $pStatement = LVPDatabase :: getInstance () -> prepare (
                        'SELECT
                                ip_address,
                                COUNT(ip_address) num_ip
                        FROM
                                samp_addresses
                        WHERE
                                nickname = ?
                        GROUP BY
                                ip_address
                        ORDER BY
                                num_ip DESC
                        LIMIT 15'
                );
                if ($pStatement === false)
                {
                        echo 'There seems to be a problem with the database. Try again later.';
                        return LVPCommand :: OUTPUT_ERROR;
                }
                $pStatement -> bind_param ('s', $sParams);
                $pStatement -> bind_result ($nLongIp, $nNumIps);
                
                if ($pStatement -> execute ())
                {
                        $pLocation = new Location ();
                        $aCountries = array ();
                        
                        while ($pStatement -> fetch ())
                        {
                                $aInfo = $pLocation -> lookup (long2ip ($nLongIp));
                                
                                if ($aInfo ['country'] == 'Unknown') continue;
                                
                                if (!isset ($aCountries [$aInfo ['country']]))
                                {
                                        $aCountries [$aInfo ['country']] = array
                                        (
                                                'Num'  => 0,
                                                'City' => array ()
                                        );
                                }
                                $aCountries [$aInfo ['country']]['Num'] += $nNumIps;
                                
                                if ($aInfo ['city'] == '' || $aInfo ['city'] == '--') continue;
                                
                                if (!isset ($aCountries [$aInfo ['country']]['City'][$aInfo ['city']]))
                                {
                                        $aCountries [$aInfo ['country']]['City'][$aInfo ['city']] = 0;
                                }
                                $aCountries [$aInfo ['country']]['City'][$aInfo ['city']] ++;
                        }
                        
                        echo ModuleBase :: COLOUR_TEAL . '* Location info for "' . $sParams . '"';
                        echo ModuleBase :: CLEAR . ': ';
                        
                        $bFirst = true;
                        foreach ($aCountries as $sCountry => $aInfo)
                        {
                                if ($bFirst) $bFirst = false;
                                else         echo ', ';
                                
                                echo $sCountry . ModuleBase :: COLOUR_DARKGREY . ' (';
                                
                                $bFirst = true;
                                foreach ($aInfo ['City'] as $sCity => $nNum)
                                {
                                        if ($bFirst) $bFirst = false;
                                        else         echo ', ';
                                        
                                        echo $sCity;
                                        
                                        if ($nNum > 1)
                                        {
                                                echo ' x' . $nNum;
                                        }
                                }
                                $bFirst = false;
                                
                                echo ')' . ModuleBase :: CLEAR;
                        }
                        
                        return LVPCommand :: OUTPUT_NORMAL;
                }
                else
                {
                        echo 'Error with the database: ' . $pStatement -> error;
                        return LVPCommand :: OUTPUT_ERROR;
                }
        }
        
        /**
         * Probably one of the most and most important commands of the whole bot
         * is handled in this quite large method. This command will determine
         * what kind of information is requested first. If given an IP or an IP
         * range, the command will return all nicknames found with that IP or in
         * that IP range. If given a nickname, it will simply return all the IPs
         * found with that nickname. Some additional parsing and adjustments in
         * the query make the output easily readable and understandable.
         * 
         * @param integer $nLevel The level we're operating at.
         * @param string $sTrigger The trigger that set this in motion.
         * @param string $sParams All of the text following the trigger.
         * @param array $aParams Same as above, except split into an array.
         * @return string
         *
         * @todo Holy shit, future me, please refactor this. This method is way too loooooooooooooooooooooooooooooooong!
         */
        private function handleIpInfo($nLevel, $sTrigger, $sParams, $aParams) {
                if ($sParams !== null) {
                        $sParams = Util::stripFormat($sParams);
                }

                if ($sParams === null || $sParams === '' || count($aParams) > 1) {
                        echo $sTrigger . ' IP // ' .
                                $sTrigger . ' Name // ' .
                                $sTrigger . ' ID';
                        return LVPCommand::OUTPUT_USAGE;
                }
                
                $bForceName = false;

                if (strpos($sTrigger, 'name') !== false) {
                        $bForceName = true;
                }
                
                if ($this->isValidId($sParams)) {
                        $pPlayer = $this->m_pModule['Players']->getPlayer($sParams);
                        
                        if ($pPlayer == null) {
                                echo 'ID not found.';
                                return LVPCommand::OUTPUT_ERROR;
                        }
                        
                        $sParams = $pPlayer['Nickname'];
                }

                $sOrigParams = $sParams;
                
                $nType = self::TYPE_NICKNAME;
                if (!$bForceName) {
                        if ($this->isValidIpV6($sParams)) {
                                echo 'Sorry, no IPv6 support.';
                                return LVPCommand::OUTPUT_ERROR;
                        }
                        else if ($this->isValidIp($sParams)) {
                                $nType = self::TYPE_IP;
                        }
                        else if ($this->isRangedIp($sParams)) {
                                $nType = self::TYPE_RANGED_IP;
                        }
                }
                
                
                if ($nLevel < LVP::LEVEL_MANAGEMENT && ($nType == self::TYPE_NICKNAME || $nType == self::TYPE_IP)) {
                        if ($nType == self::TYPE_NICKNAME) {
                                $sTable = 'samp_address_hide_nickname';
                                $sField = 'nickname';
                                $sBindParam = 's';
                                $sQueryParam = $sParams;
                        }
                        else if ($nType == self::TYPE_IP) {
                                $sTable = 'samp_address_hide_ip';
                                $sField = 'ip_address';
                                $sBindParam = 'i';
                                $sQueryParam = $this->ip2long($sParams);
                        }
                        else {
                                // ?
                                echo 'Unknown error occurred.';
                                return LVPCommand::OUTPUT_ERROR;
                        }
                        
                        $pStatement = LVPDatabase::getInstance()->prepare(
                                'SELECT
                                        display_string
                                FROM
                                        ' . $sTable . '
                                WHERE
                                        ' . $sField . ' = ?'
                        );

                        if ($pStatement === false)
                        {
                                echo 'There seems to be a problem with the database. Try again later.';
                                return LVPCommand :: OUTPUT_ERROR;
                        }
                        $pStatement -> bind_param ($sBindParam, $sQueryParam);
                        $pStatement -> bind_result ($sFakeString);
                        $pStatement -> execute ();
                        $pStatement -> store_result ();
                        
                        if ($pStatement -> num_rows != 0)
                        {
                                $pStatement -> fetch ();
                                // Hide it.
                                if ($sFakeString == null) {
                                	echo ModuleBase :: COLOUR_TEAL . '* No results found.';
                                	return LVPCommand :: OUTPUT_NORMAL;
                                } else {
                                	echo ModuleBase :: COLOUR_TEAL . '* Result with "' . $sParams . '"';
                                	echo ModuleBase :: CLEAR . ': ' . $sFakeString;
                                	return LVPCommand :: OUTPUT_NORMAL;
                                }
                        }
                        
                        $pStatement -> close ();
                }
                
                $pDatabase = LVPDatabase :: getInstance ();
                $sQuery =
                        'SELECT
                                nickname,
                                GROUP_CONCAT(DISTINCT ip_address) ip_address,
                                COUNT(*) num,
                                MAX(join_date) join_date,
                                GROUP_CONCAT(DISTINCT country) country,
                                GROUP_CONCAT(DISTINCT user_id) user_id
                        FROM
                                samp_addresses
                        WHERE ';
                
                switch ($nType)
                {
                        case self :: TYPE_IP:
                        {
                                $sQuery .= 'ip_address = ' . $this -> ip2long ($sParams);
                                break ;
                        }
                        
                        case self :: TYPE_RANGED_IP:
                        {
                                $nMinIp = $this -> ip2long (str_replace ('*', '0', $sParams));
                                $nMaxIp = $this -> ip2long (str_replace ('*', '255', $sParams));
                                
                                $sQuery .= 'ip_address >= ' . $nMinIp . ' AND ip_address <= ' . $nMaxIp;
                                break ;
                        }
                        
                        case self :: TYPE_NICKNAME:
                        {
                                $sQuery .= 'nickname = "' . $pDatabase -> real_escape_string ($sParams) . '"';
                                break ;
                        }
                }
                
                if ($nLevel < LVP :: LEVEL_MANAGEMENT && ($nType == self :: TYPE_NICKNAME || $nType == self :: TYPE_IP))
                {
                        if ($nType == self :: TYPE_NICKNAME)
                        {
                                $sTable = 'samp_address_hide_ip';
                                $sField = 'ip_address';
                                
                        }
                        else if ($nType == self :: TYPE_IP)
                        {
                                $sTable = 'samp_address_hide_nickname';
                                $sField = 'nickname';
                                
                        }
                        
                        $sQuery .= ' AND ' . $sField . ' NOT IN(
                                SELECT ' . $sField . '
                                FROM ' . $sTable . '
                        )';
                }
                
                $sQuery .= ' GROUP BY ';
                
                switch ($nType)
                {
                        case self::TYPE_IP:
                        case self::TYPE_RANGED_IP:
                                $sQuery .= 'nickname';
                                break;

                        case self::TYPE_NICKNAME:
                                $sQuery .= 'ip_address';
                                break;
                }
                
                $sQuery .= 
                        ' ORDER BY
                                join_date DESC
                        LIMIT 15';
                
                $pResult = $pDatabase -> query ($sQuery);
                if ($pResult === false)
                {
                        echo 'There seems to be a problem with the database. Try again later.';
                        return LVPCommand :: OUTPUT_ERROR;
                }
                
                if ($pResult -> num_rows == 0)
                {
                        echo ModuleBase :: COLOUR_TEAL . '* No results found.';
                        return LVPCommand :: OUTPUT_NORMAL;
                }
                
                echo ModuleBase :: COLOUR_TEAL . '* ';
                switch ($nType)
                {
                        case self :: TYPE_IP:
                        {
                                echo 'Names from IP';
                                break ;
                        }
                        
                        case self :: TYPE_RANGED_IP:
                        {
                                echo 'Names in IP range';
                                break ;
                        }
                        
                        case self :: TYPE_NICKNAME:
                        {
                                echo 'IPs with name';
                                break ;
                        }
                }
                echo ' "' . $sOrigParams . '"' . ModuleBase :: CLEAR . ': ';
                
                $nUnderlineDiff = 86400 * 3;
                $nTime     = time ();
                $bFirst    = true;
                $pLocation = null; // Inited when needed.
                
                while (($aRow = $pResult -> fetch_assoc ()) !== null)
                {
                        if ($bFirst) $bFirst = false;
                        else         echo ', ';
                        
                        $bUnderline = false;
                        if ($nTime - strtotime ($aRow ['join_date']) <= $nUnderlineDiff)
                        {
                                $bUnderline = true;
                                echo ModuleBase :: UNDERLINE;
                        }
                        
                        switch ($nType)
                        {
                                case self :: TYPE_NICKNAME:
                                {
                                        echo long2ip ($aRow ['ip_address']);
                                        break ;
                                }
                                
                                case self :: TYPE_IP:
                                case self :: TYPE_RANGED_IP:
                                {
                                        echo $aRow ['nickname'];
                                        break ;
                                }
                        }
                        
                        if ($bUnderline)
                        {
                                // Reset
                                echo ModuleBase :: UNDERLINE;
                        }
                        
                        if ($aRow ['country'] == '')
                        {
                                if ($pLocation == null)
                                {
                                        $pLocation = new Location ();
                                }
                                
                                // Not yet retrieved, do it ourselves.
                                $aInfo = $pLocation -> lookup (long2ip (explode(',', $aRow ['ip_address'])[0]));
                                $aRow ['country'] = $aInfo ['country_2'];
                        }
                        
                        if ($aRow ['num'] > 1 || ($aRow ['country'] != '--' && $aRow ['country'] != ''))
                        {
                                // Darker color so that other information stays readable.
                                echo ModuleBase :: COLOUR_DARKGREY;
                                
                                if ($aRow ['num'] > 1)
                                {
                                        echo ' (x' . $aRow ['num'] . ')';
                                }
                                
                                if ($aRow ['country'] != '--' && $aRow ['country'] != '')
                                {
                                        echo ' (' . $aRow ['country'] . ')';
                                }
                                
                                echo ModuleBase :: CLEAR;
                        }
                }
                
                return LVPCommand :: OUTPUT_NORMAL;
        }
}
