<?php
/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * @author Dik Grapendaal <dik.grapendaal@gmail.com>
 * 
 * $Id: LVPConfiguration.php 187 2010-11-27 02:40:21Z Dik $
 */
class LVPConfiguration extends LVPEchoHandlerClass implements ArrayAccess
{
        
        /**
         * The array holding all of the configurations of all the classes
         * in the LVPEchoHandler module.
         * 
         * @var array
         */
        
        private $m_aConfiguration = array ();
        
        /**
         * In order to have at least a bit of control when setting a value
         * to a configuration directive, we support filtering using PHP's
         * filter_var () function. All filters are supported. If a directive
         * doesn't have a filter specified in this array, we don't filter it.
         * 
         * @see http://nl3.php.net/manual/en/filter.filters.php
         * @var array
         */
        
        private $m_aDirectiveFilter = array ();
        
        /**
         * The constructor has been overridden to provide functionality of
         * loading the settings.
         * 
         * @param LVPEchoHandler $pModule The LVPEchoHandler module we're residing in.
         */
        
        public function __construct (LVPEchoHandler $pModule)
        {
                parent :: __construct ($pModule);
                
                if (file_exists ('Data/LVP/Configuration.dat'))
                {
                        /** Load 'em up, Scotty. **/
                        $aLoadedSettings = unserialize (file_get_contents ('Data/LVP/Configuration.dat'));
                        $this -> m_aConfiguration     = $aLoadedSettings [0];
                        $this -> m_aDirectiveFilter   = $aLoadedSettings [1];
                }
                
                $this -> registerCommands ();
        }
        
        /**
         * The destructor will save the state of all the settings when the
         * bot is shutting down. This way, we can restore them all when we
         * return back in service.
         */
        
        public function __destruct ()
        {
                file_put_contents ('Data/LVP/Players.dat', serialize (array
                (
                        $this -> m_aConfiguration,
                        $this -> m_aDirectiveFilter
                )));
        }
        
        /**
         * In order to be able to change something through IRC, we need to 
         * register some commands. Guess what? You'll see that happen in this
         * very method.
         */
        
        private function registerCommands ()
        {
                $aCommands = array
                (
                        '!lvpset'            => LVP :: LEVEL_MANAGEMENT,
                        '!lvpget'            => LVP :: LEVEL_MANAGEMENT,
                        '!lvpdirectives'     => LVP :: LEVEL_MANAGEMENT
                );
                
                foreach ($aCommands as $sTrigger => $nLevel)
                {
                        $this -> m_pModule -> cmds -> register (new LVPCommand
                        (
                                $sTrigger, $nLevel, array ($this, 'handleCommands')
                        ));
                }
        }
        
        /**
         * This method gets invoked as soon as we're expected to execute a command.
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
        
        public function handleCommands ($pModule, $nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams)
        {
                switch ($sTrigger)
                {
                        case '!lvpget':        { return $this -> handleDirectiveGet ($aParams);      }
                        case '!lvpset':        { return $this -> handleDirectiveSet ($aParams);      }
                        case '!lvpdirectives': { return $this -> handleDirectiveOverview (); }
                }
        }
        
        /**
         * In order to see the current value of a certain directive through IRC,
         * we need a method to handle that. This is it.
         * 
         * @param array $aParams The parameters given to the command.
         */
        
        private function handleDirectiveGet ($aParams)
        {
                if (count ($aParams) < 1)
                {
                        echo '!lvpget Directive';
                        return LVPCommand :: OUTPUT_USAGE;
                }
                
                if (!isset ($this [$aParams [0]]))
                {
                        echo 'Unknown directive, please see !lvpdirectives.';
                        return LVPCommand :: OUTPUT_ERROR;
                }
                
                echo 'Current value of "' . $aParams [0] . '": ' . $this [$aParams [0]];
                return LVPCommand :: OUTPUT_INFO;
        }
        
        /**
         * Perhaps the most important feature of this class, the directive set IRC
         * command. A value of a directive can be changed through this command, which
         * is handled by this method. If a directive has a filter setup, it even checks
         * the input.
         * 
         * @param array $aParams The parameters given to the command.
         */
        
        private function handleDirectiveSet ($aParams)
        {
                if (count ($aParams) < 2)
                {
                        echo '!lvpset Directive Value';
                        return LVPCommand :: OUTPUT_USAGE;
                }
                
                if (!isset ($this [$aParams [0]]))
                {
                        echo 'Unknown directive, please see !lvpdirectives.';
                        return LVPCommand :: OUTPUT_ERROR;
                }
                
                list ($sPrefix, $sName) = explode ('.', $aParams [0]);
                if (!$this -> validateDirectiveValue ($sPrefix, $sName, $aParams [1]))
                {
                        echo 'Invalid value specified for "' . $aParams [0] . '"';
                        
                        $sType = '';
                        switch ($this -> m_aDirectiveFilter [$aParams [0]])
                        {
                                case FILTER_VALIDATE_BOOLEAN:   { $sType = 'boolean';              break; }
                                case FILTER_VALIDATE_EMAIL:     { $sType = 'e-mail address';       break; }
                                case FILTER_VALIDATE_FLOAT:     { $sType = 'float';                break; }
                                case FILTER_VALIDATE_INT:       { $sType = 'integer';              break; }
                                case FILTER_VALIDATE_IP:        { $sType = 'IP address';           break; }
                                case FILTER_VALIDATE_REGEXP:    { $sType = 'regular expression';   break; }
                                case FILTER_VALIDATE_URL:       { $sType = 'URL';                  break; }
                                
                                default: { echo '.'; return LVPCommand :: OUTPUT_ERROR; }
                        }
                        
                        echo '; ' . $sType . ' expected.';
                        
                        return LVPCommand :: OUTPUT_ERROR;
                }
                
                $mOldValue = $this [$aParams [0]];
                $this [$aParams [0]] = $aParams [1];
                
                echo 'Changed value of "' . $aParams [0] . '" from ' . self::stringify($mOldValue) . ' to ' . self::stringify($this[$aParams[0]]) . '.';
                return LVPCommand :: OUTPUT_INFO;
        }

        private static function stringify($value) {
                if ($value === true) {
                        return 'true';
                } else if ($value === false) {
                        return 'false';
                }
                return $value;
        }
        
        /**
         * Outputs an overview of the available directives for the !lvpdirectives
         * command.
         */
        
        private function handleDirectiveOverview ()
        {
                echo 'Available directives: ' . implode (', ', array_keys ($this -> m_aConfiguration));
                return LVPCommand :: OUTPUT_INFO;
        }
        
        /**
         * Adding a directive for an LVPEchoHandler class is easy using this method.
         * The prefix is the same as the abbreviated form of the class in the module.
         * The name can be anything, just as the default value. The filter is optional,
         * but recommended.
         * 
         * @param string $sPrefix The abbreviated name of the class we're registering a directive for.
         * @param string $sName The name of the directive in that class.
         * @param mixed $mDefault The default value of the setting.
         * @param integer $nFilterValidation The validation applied to the directive values.
         */
        
        public function addDirective ($sPrefix, $sName, $mDefault = 0, $nFilterValidation = -1)
        {
                if ($this -> isDirective ($sPrefix, $sName))
                {
                        /** Already known? Skip the formalities, we already got it from saved settings. **/
                        return;
                }
                
                if (!isset ($this -> m_pModule [$sPrefix]))
                {
                        /** Unknown handler class? Don't bother then. **/
                        return;
                }
                
                $this -> m_aConfiguration [$sPrefix . '.' . $sName] = $mDefault;
                
                if ($nFilterValidation != -1)
                {
                        $this -> m_aDirectiveFilter [$sPrefix . '.' . $sName] = $nFilterValidation;
                }
        }
        
        /**
         * Checks whether a directive exists.
         * 
         * @param string $sPrefix The abbreviated name of the class we're registering a directive for.
         * @param string $sName The name of the directive in that class.
         * @return boolean
         */
        
        public function isDirective ($sPrefix, $sName)
        {
                return isset ($this -> m_aConfiguration [$sPrefix . '.' . $sName]);
        }
        
        /**
         * Returns the value for the requested setting.
         *
         * @param string $sPrefix The abbreviated name of the class.
         * @param string $sName The name of the directive in that class.
         */
        
        public function getDirective ($sPrefix, $sName)
        {
                return $this -> m_aConfiguration [$sPrefix . '.' . $sName];
        }
        
        /**
         * Checks if the given value is a valid value for the specified directive.
         *
         * @param string $sPrefix The abbreviated name of the class.
         * @param string $sName The name of the directive in that class.
         * @param mixed $mValue The value to test.
         * @return boolean
         */
        
        public function validateDirectiveValue ($sPrefix, $sName, $mValue)
        {
                if (isset ($this -> m_aDirectiveFilter [$sPrefix . '.' . $sName]))
                {
                        $nFilter = $this -> m_aDirectiveFilter [$sPrefix . '.' . $sName];
                        
                        $aOptions = array ();
                        if ($nFilter == FILTER_VALIDATE_BOOLEAN)
                        {
                                $aOptions ['flags'] = FILTER_NULL_ON_FAILURE;
                        }
                        
                        $mValue = filter_var ($mValue, $nFilter, $aOptions);
                        
                        if ($nFilter == FILTER_VALIDATE_BOOLEAN && $mValue === null)
                        {
                                return false;
                        }
                        else if ($nFilter != FILTER_VALIDATE_BOOLEAN && $mValue === false)
                        {
                                return false;
                        }
                }
                
                return true;
        }
        
        /**
         * Sets a directive to the specified value. If a filter has been specified,
         * it will be filtered using that first. Returns a boolean indicating whether
         * the value has been set or not.
         *
         * @param string $sPrefix The abbreviated name of the class.
         * @param string $sName The name of the directive in that class.
         * @param mixed $mValue The value to set it to.
         * @return boolean
         */
        
        public function setDirective ($sPrefix, $sName, $mValue)
        {
                if (!$this -> validateDirectiveValue ($sPrefix, $sName, $mValue))
                {
                        return false;
                }
                
                $this -> m_aConfiguration [$sPrefix . '.' . $sName] = $mValue;
                
                return true;
        }
        
        /**
         * Unsets the value for the requested setting.
         *
         * @param string $sPrefix The abbreviated name of the class.
         * @param string $sName The name of the directive in that class.
         */
        
        public function unsetDirective ($sPrefix, $sName)
        {
                unset ($this -> m_aConfiguration [$sPrefix . '.' . $sName]);
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
                return isset ($this -> m_aConfiguration [$mKey]);
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
                return $this -> m_aConfiguration [$mKey];
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
                if (isset ($this -> m_aDirectiveFilter [$mKey]))
                {
                        $nFilter = $this -> m_aDirectiveFilter [$mKey];
                        
                        $aOptions = array ();
                        if ($nFilter == FILTER_VALIDATE_BOOLEAN)
                        {
                                $aOptions ['flags'] = FILTER_NULL_ON_FAILURE;
                        }
                        
                        $mValue = filter_var ($mValue, $nFilter, $aOptions);
                        
                        if ($nFilter == FILTER_VALIDATE_BOOLEAN && $mValue === null)
                        {
                                return;
                        }
                        else if ($nFilter != FILTER_VALIDATE_BOOLEAN && $mValue === false)
                        {
                                return;
                        }
                }
                
                $this -> m_aConfiguration [$mKey] = $mValue;
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
                unset ($this -> m_aConfiguration [$mKey]);
        }
}
?>