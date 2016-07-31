<?php
require 'LVPPlayer.php';

/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPPlayerManager extends LVPEchoHandlerClass implements ArrayAccess, Countable, IteratorAggregate
{
        const STATE_FILE = 'Data/LVP/Players.dat';
        
        /**
         * This array keeps track of every player ingame, with only information
         * from the echo messages. The key is the ID of the player ingame. Every
         * value is an LVPPlayer object.
         * 
         * @var array
         */
        private $m_aPlayers;
        
        /**
         * The constructor has been overridden to provide functionality of
         * reloading the players that were serialized from a previous session.
         * 
         * @param LVPEchoHandler $pModule The LVPEchoHandler module we're residing in.
         */
        public function __construct (LVPEchoHandler $pModule) {
            parent::__construct($pModule);
            
            $this->loadState();
        }
        
        /**
         * The destructor will save the state of all the players ingame when the
         * bot is shutting down. This way, we can restore them all when we
         * return back in service.
         */
        public function __destruct() {
            $this->saveState();
        }

        public function loadState() {
            if (file_exists (self::STATE_FILE)) {
                $this->m_aPlayers = unserialize(file_get_contents(self::STATE_FILE));
                
                unlink (self::STATE_FILE);
            }
        }

        public function saveState() {
            file_put_contents(self::STATE_FILE, serialize($this->m_aPlayers));
        }
        
        /**
         * This method will serialize the player data, so that we can restore
         * them all later. After a restart, for example.
         * 
         * @return string
         */
        public function serialize ()
        {
                return serialize ($this -> m_aPlayers);
        }
        
        /**
         * This method will unserialized the serialized data and save the data
         * into their respective properties.
         * 
         * @param string $sSerialized The serialized data.
         */
        public function unserialize ($sSerialized)
        {
                $this -> m_aPlayers = unserialize ($sSerialized);
        }
        
        /**
         * This method will clean up all the players ever created in this class.
         * This is used when initing and when a gamemode init occurs.
         */
        public function clearPlayers ()
        {
                $this -> m_aPlayers = array ();
        }
        
        /**
         * Retrieves all the players from the gameserver using an UDP query and
         * sets up all objects for every player.
         * 
         * @return boolean
         */
        
        public function syncPlayers ()
        {
                try
                {
                        $pQuery   = new QuerySAMPServer (LVP :: GAMESERVER_IP, LVP :: GAMESERVER_PORT, 0, 500000);
                        $aPlayers = $pQuery -> getDetailedPlayers ();
                        $nPlayers = count ($aPlayers);
                        
                        if ($nPlayers == 0)
                        {
                                // Sure?
                                $aPlayers = $pQuery -> getDetailedPlayers ();
                                $nPlayers = count ($aPlayers);
                        }
                }
                catch (QueryServerException $pException)
                {
                        return false;
                }
                
                $db = LVPDatabase :: getInstance ();

                $oldPlayers = $this->m_aPlayers;

                $this->clearPlayers();
                
                foreach ($aPlayers as $aPlayerInfo)
                {
                        $nId = $aPlayerInfo ['PlayerID'];

                        // Check if we know the player already.
                        if (isset($oldPlayers[$nId])) {
                            // Yep we do. Put him back.
                            $this->m_aPlayers[$nId] = $oldPlayers[$nId];
                        }

                        $this -> setPlayerKey ($nId, 'Nickname', $aPlayerInfo ['Nickname']);
                        
                        if ($this -> getPlayerKey ($nId, 'JoinTime') === 0)
                        {
                                $this -> setPlayerKey ($nId, 'JoinTime', time ());
                        }
                        
                        if ($this -> getPlayerKey ($nId, 'Level') === 0)
                        {
                                $pResult = $db -> query (
                                        'SELECT u.user_id
                                        FROM lvp_mainserver.users_nickname n
                                        LEFT JOIN lvp_mainserver.users u ON u.user_id = n.user_id
                                        LEFT JOIN lvp_mainserver.users_mutable m ON m.user_id = u.user_id
                                        WHERE n.nickname = "' . $db->real_escape_string($aPlayerInfo['Nickname']) . '"');
                                
                                if ($pResult !== false && $pResult -> num_rows > 0)
                                {
                                        list ($nProfileId) = $pResult -> fetch_row ();
                                        $pResult -> free ();
                                        $this -> getPlayer ($nId) -> fetchInformation ($nProfileId);
                                }
                        }
                }
                
                return true;
        }
        
        /**
         * This method creates a new LVPPlayer with the supplied ID for us and
         * returns a pointer to the new LVPPlayer object.
         * 
         * @param integer $nId The ID for this player.
         * @return LVPPlayer
         */
        public function createPlayer ($nId)
        {
                $nId = (int) $nId;
                $this -> m_aPlayers [$nId] = new LVPPlayer ($nId);
                
                return $this -> getPlayer ($nId);
        }
        
        /**
         * Returns a boolean indicating we know a player with the given ID.
         * 
         * @param integer $nId The ID of the player we want to know the existence of.
         * @return boolean
         */
        public function isPlayer ($nId)
        {
                return $this -> getPlayer ($nId) !== null;
        }
        
        /**
         * This method deletes the player with the given ID and returns the
         * object that was deleted.
         * 
         * @param integer $nId The ID of the player we want deleted.
         * @return LVPPlayer
         */
        public function deletePlayer ($nId)
        {
                $pPlayer = $this -> getPlayer ($nId);
                
                if ($pPlayer == null)
                {
                        return null;
                }
                
                unset ($this -> m_aPlayers [(int) $nId]);
                
                return $pPlayer;
        }
        
        /**
         * This method returns the LVPPlayer object for the player with the
         * specified ID.
         * 
         * @param integer $nId The ID of the player we want info from.
         * @return LVPPlayer
         */
        public function getPlayer ($nId)
        {
                $nId = (int) $nId;
                if (isset ($this -> m_aPlayers [$nId]))
                {
                        return $this -> m_aPlayers [$nId];
                }
                
                return null;
        }
        
        /**
         * This method will try to look up the LVPPlayer object belonging to the
         * supplied nickname. If nothing's found, null is returned. It only
         * matches exact names, case insensitive.
         * 
         * @param string $sNickname The nickname we're looking for.
         * @return LVPPlayer
         */
        public function getPlayerByName ($sNickname)
        {
                $sNickname = strtolower ($sNickname);
                foreach ($this -> m_aPlayers as $nId => $pPlayer)
                {
                        if (strtolower ($pPlayer ['Nickname']) == $sNickname)
                        {
                                return $this -> m_aPlayers [$nId];
                        }
                }
                
                return null;
        }
        
        /**
         * Gets the ID of the given player name.
         * 
         * @param string $sNickname The nickname we're looking for.
         * @return integer
         */
        
        public function getPlayerId ($sNickname)
        {
                $sNickname = strtolower ($sNickname);
                foreach ($this -> m_aPlayers as $nId => $pPlayer)
                {
                        if (strtolower ($pPlayer ['Nickname']) == $sNickname)
                        {
                                return $nId;
                        }
                }
                
                return -1;
        }
        
        /**
         * Returns the value for the player with the given ID with the given
         * key. Returns null when either the player cannot be found or a non-
         * existing key has been given.
         * 
         * @param integer $nId The ID of the player we want information from.
         * @param string $sKey The information key we want to know of.
         */
        public function getPlayerKey ($nId, $sKey)
        {
                $pPlayer = $this -> getPlayer ($nId);
                if ($pPlayer == null)
                {
                        return null;
                }
                
                return $pPlayer [$sKey];
        }
        
        /**
         * This method sets the given to the supplied value for the player with
         * the given ID. If there's no LVPPlayer object for that ID, a new one
         * is automatically created.
         * 
         * @param integer $nId The ID of the player we're modifying.
         * @param string $sKey The information key we want to set.
         * @param mixed $mValue The value we want to set it to.
         */
        public function setPlayerKey ($nId, $sKey, $mValue)
        {
                if ($this -> getPlayer ($nId) == null)
                {
                        $this -> createPlayer ($nId);
                }
                
                $pPlayer = $this -> getPlayer ($nId);
                $pPlayer [$sKey] = $mValue;
        }
        
        /**
         * Returns a boolean indicating whether there's crew ingame. This includes
         * both permanent and temporary crew.
         * 
         * @return boolean
         */
        
        public function isCrewIngame ()
        {
                foreach ($this -> m_aPlayers as $pPlayer)
                {
                        if ($pPlayer ['Level'] >= LVP :: LEVEL_ADMINISTRATOR ||
                            $pPlayer ['TempLevel'] >= LVP :: LEVEL_ADMINISTRATOR)
                        {
                                return true;
                        }
                }
                
                return false;
        }
        
        /**
         * Returns a boolean indicating whether there's permanent crew ingame. This
         * does not include temporary crew.
         * 
         * @return boolean
         */
        
        public function isPermanentCrewIngame ()
        {
                foreach ($this -> m_aPlayers as $pPlayer)
                {
                        if ($pPlayer ['Level'] >= LVP :: LEVEL_ADMINISTRATOR)
                        {
                                return true;
                        }
                }
                
                return false;
        }
        
        /**
         * Returns a boolean indicating whether there's temporary crew ingame. This
         * does not include permanent crew.
         * 
         * @return boolean
         */
        
        public function isTempCrewIngame ()
        {
                foreach ($this -> m_aPlayers as $pPlayer)
                {
                        if ($pPlayer ['TempLevel'] >= LVP :: LEVEL_ADMINISTRATOR)
                        {
                                return true;
                        }
                }
                
                return false;
        }
        
        /**
         * The ArrayAccess interface provides us with the ability to use an 
         * instance of this class as an array. The operations executed with that
         * syntax, are redirected to these methods.
         * 
         * This method gets called when an isset () call has been performed on
         * an array index of this class.
         * 
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         * @return boolean
         */
        public function offsetExists ($mKey)
        {
                if (is_numeric ($mKey))
                {
                        return $this -> isPlayer ((int) $mKey);
                }
                
                return false;
        }
        
        /**
         * The ArrayAccess interface provides us with the ability to use an 
         * instance of this class as an array. The operations executed with that
         * syntax, are redirected to these methods.
         * 
         * Invokation of this method will occur when one wants to get a value
         * from this class using the array syntax.
         * 
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         * @return mixed
         */
        public function offsetGet ($mKey)
        {
                if (is_numeric ($mKey))
                {
                        return $this -> getPlayer ((int) $mKey);
                }
                
                return null;
        }
        
        /**
         * The ArrayAccess interface provides us with the ability to use an 
         * instance of this class as an array. The operations executed with that
         * syntax, are redirected to these methods.
         * 
         * This method will be invoked when an index on this class has been
         * set using the array syntax.
         * 
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         * @param mixed $mKey The new value for the specified key.
         */
        public function offsetSet ($mKey, $mValue)
        {
                if (is_numeric ($mKey) && strpos ($mValue, '=') !== false)
                {
                        list ($sKey, $sValue) = explode ($mValue, '=', 2);
                        $this -> setPlayerKey ((int) $mKey, $sKey, $sValue);
                }
        }
        
        /**
         * The ArrayAccess interface provides us with the ability to use an 
         * instance of this class as an array. The operations executed with that
         * syntax, are redirected to these methods.
         * 
         * This method gets called when an unset () call has been performed on
         * an array index of this class.
         * 
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         */
        public function offsetUnset ($mKey)
        {
                if (is_numeric ($mKey))
                {
                        $this -> deletePlayer ((int) $mKey);
                }
        }
        
        /**
         * Returns the number of players ingame.
         * 
         * @return integer
         */
        
        public function count ()
        {
                return count ($this -> m_aPlayers);
        }
        
        /**
         * Returns an ArrayIterator for the players in this class.
         * 
         * @return ArrayIterator
         */
        
        public function getIterator ()
        {
                return new ArrayIterator ($this -> m_aPlayers);
        }
}
