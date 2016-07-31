<?php
/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPPlayer implements ArrayAccess, Serializable
{
        
        /**
         * The ingame ID of this player.
         * 
         * @var integer
         */
        private $m_nId;
        
        /**
         * The ID of this player in the database. This is referred to as the
         * profile ID in the LVP gamemode, so I'll use that as well. If 0, it
         * means this player is unregistered.
         * 
         * @var integer
         */
        private $m_nProfileId;
        
        /**
         * The ingame nickname of this player.
         * 
         * @var string
         */
        private $m_sNickname;
        
        /**
         * The official level of this player.
         * 
         * @var integer
         */
        private $m_nLevel;
        
        /**
         * The temporary level of this player ingame. This is 0 if it's no
         * different from the official level.
         * 
         * @var integer
         */
        private $m_nTempLevel;
        
        /**
         * The current IP address of the player.
         * 
         * @var string
         */
        private $m_sIp;
        
        /**
         * The timestamp of the moment this player joined the server.
         * 
         * @var integer
         */
        private $m_nJoinTime;
        
        /**
         * The timestamp of the moment the player logged in. This stays 0 when
         * he doesn't log in.
         * 
         * @var integer
         */
        private $m_nLogInTime;
        
        /**
         * This array contains all the keys supported by the ArrayAccess
         * methods. This array is also used to serialize all information
         * available in this class, so that we load it up again at a later time.
         * 
         * @var array
         */
        private static $s_aInfoKeys;
        
        /**
         * This class represents a single player on the LVP server. It keeps
         * track of random stats as well as some less random information, like
         * the ID and nickname of the player.
         * 
         * @param integer $nId The ID of this new player.
         */
        public function __construct ($nId)
        {
                $this -> setInfoKeys ();
                
                // Set all variables to their defaults.
                foreach (self :: $s_aInfoKeys as $sKey)
                {
                        unset ($this [$sKey]);
                }
                
                $this ['ID'] = $nId;
        }
        
        /**
         * This method will set the all important information keys array, where
         * the serialize functionality works with, as well as the ArrayAccess
         * functionality.
         */
        private function setInfoKeys ()
        {
                if (!empty (self :: $s_aInfoKeys))
                {
                        return;
                }
                
                self :: $s_aInfoKeys = array
                (
                        'ID', 'ProfileID', 'Nickname', 'Level', 'TempLevel',
                        'IP', 'JoinTime', 'LogInTime'
                );
        }
        
        /**
         * This method will serialize all the information in this object into a
         * single array. Updating this method is not necessary when new
         * properties are added to this object in the future. Only the InfoKeys
         * array should be updated with all the properties.
         * 
         * @return string
         */
        public function serialize ()
        {
                $aInformation = array ();
                foreach (self :: $s_aInfoKeys as $sKey)
                {
                        $aInformation [$sKey] = $this [$sKey];
                }
                
                return serialize ($aInformation);
        }
        
        /**
         * This method will unserialize the date that was serialized earlier in
         * the serialize () method, and set the properties to the values as
         * specified in the serialized data.
         * 
         * @param string $sSerialized The serialized data.
         */
        public function unserialize ($sSerialized)
        {
                $this -> setInfoKeys ();
                
                $aInformation = unserialize ($sSerialized);
                
                foreach ($aInformation as $sKey => $mValue)
                {
                        $this [$sKey] = $mValue;
                }
        }
        
        /**
         * LVP's central database contains a shed load of information. Using
         * this method, we'll fetch whatever we may need from there and store it
         * in some variable until it's needed. At the moment this method only
         * fetches the crew status of a user.
         * 
         * @param integer $nProfileId The unique ID of the user in the database.
         * @return boolean
         */
        
        public function fetchInformation ($nProfileId)
        {
                $this -> m_nProfileId = $nProfileId;
                
                $db = LVPDatabase :: getInstance ();
                $pResult = $db -> query (
                        'SELECT
                                u.level, u.is_developer
                        FROM
                                lvp_mainserver.users u
                        WHERE
                                u.user_id = ' . (int) $nProfileId);
                
                if ($pResult !== false && $pResult -> num_rows != 0)
                {
                        $aInformation = $pResult -> fetch_assoc ();
                        
                        $this ['Level'] = LVPCrewHandler::translateGamemodeLevel($aInformation['level'], $aInformation['is_developer']);
                        
                        $pResult -> free ();
                }
        }
        
        /**
         * The ArrayAccess interface provides us with the ability to use an 
         * instance of this class as an array. The operations executed with that
         * syntax, are redirected to these methods. I use this for the LVPPlayer
         * class because then I can group all the getters, setters and unsetters
         * into four methods.
         * 
         * This method gets called when an isset () call has been performed on
         * an array index of this class.
         * 
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         * @return boolean
         */
        public function offsetExists ($mKey)
        {
                return in_array ($mKey, self :: $s_aInfoKeys);
        }
        
        /**
         * The ArrayAccess interface provides us with the ability to use an 
         * instance of this class as an array. The operations executed with that
         * syntax, are redirected to these methods. I use this for the LVPPlayer
         * class because then I can group all the getters, setters and unsetters
         * into four methods.
         * 
         * Invokation of this method will occur when one wants to get a value
         * from this class using the array syntax.
         * 
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         * @return mixed
         */
        public function offsetGet ($mKey)
        {
                switch ($mKey)
                {
                        case 'ID':        { return $this -> m_nId;        }
                        case 'ProfileID': { return $this -> m_nProfileId; }
                        case 'Nickname':  { return $this -> m_sNickname;  }
                        case 'Level':     { return $this -> m_nLevel;     }
                        case 'TempLevel': { return $this -> m_nTempLevel; }
                        case 'IP':        { return $this -> m_sIp;        }
                        case 'JoinTime':  { return $this -> m_nJoinTime;  }
                        case 'LogInTime': { return $this -> m_nLogInTime; }
                }
                
                return null;
        }
        
        /**
         * The ArrayAccess interface provides us with the ability to use an 
         * instance of this class as an array. The operations executed with that
         * syntax, are redirected to these methods. I use this for the LVPPlayer
         * class because then I can group all the getters, setters and unsetters
         * into four methods.
         * 
         * This method will be invoked when an index on this class has been
         * set using the array syntax.
         * 
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         * @param mixed $mKey The new value for the specified key.
         */
        public function offsetSet ($mKey, $mValue)
        {
                switch ($mKey)
                {
                        case 'ID':        { $this -> m_nId        =    (int) $mValue;  break; }
                        case 'ProfileID': { $this -> m_nProfileId =    (int) $mValue;  break; }
                        case 'Nickname':  { $this -> m_sNickname  = (string) $mValue;  break; }
                        case 'Level':     { $this -> m_nLevel     =    (int) $mValue;  break; }
                        case 'TempLevel': { $this -> m_nTempLevel =    (int) $mValue;  break; }
                        case 'IP':        { $this -> m_sIp        = (string) $mValue;  break; }
                        case 'JoinTime':  { $this -> m_nJoinTime  =    (int) $mValue;  break; }
                        case 'LogInTime': { $this -> m_nLogInTime =    (int) $mValue;  break; }
                }
        }
        
        /**
         * The ArrayAccess interface provides us with the ability to use an 
         * instance of this class as an array. The operations executed with that
         * syntax, are redirected to these methods. I use this for the LVPPlayer
         * class because then I can group all the getters, setters and unsetters
         * into four methods.
         * 
         * This method gets called when an unset () call has been performed on
         * an array index of this class.
         * 
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         */
        public function offsetUnset ($mKey)
        {
                switch ($mKey)
                {
                        case 'ID':        { $this -> m_nId        = 0;   break; }
                        case 'ProfileID': { $this -> m_nProfileId = 0;   break; }
                        case 'Nickname':  { $this -> m_sNickname  = '';  break; }
                        case 'Level':     { $this -> m_nLevel     = 0;   break; }
                        case 'TempLevel': { $this -> m_nTempLevel = 0;   break; }
                        case 'IP':        { $this -> m_sIp        = '';  break; }
                        case 'JoinTime':  { $this -> m_nJoinTime  = 0;   break; }
                        case 'LogInTime': { $this -> m_nLogInTime = 0;   break; }
                }
        }
}
