<?php
/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPCommand implements ArrayAccess
{
        
        /**
         * These constants define the different types of output a command can
         * give. To trigger one of these, use the return statement somewhere in
         * your command code. Note however that any output must be done using
         * echo, print or any other command that will write to stdout.
         * 
         * @var integer
         */
        const   OUTPUT_NORMAL           = 0;
        const   OUTPUT_ERROR            = 1;
        const   OUTPUT_INFO             = 2;
        const   OUTPUT_NOTICE           = 3;
        const   OUTPUT_SUCCESS          = 4;
        const   OUTPUT_USAGE            = 5;
        
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
        const   MODE_IRC                = 1;
        const   MODE_CREW_CHAT          = 2;
        const   MODE_MAIN_CHAT          = 3;
        
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
         * @param string $sTrigger The trigger to listen to, this can be plain text or a regex.
         * @param integer $nLevel The minimum level required to execute this command.
         * @param mixed $mCode A callback to the code we're executing or a string with the code itself.
         * @throws Exception When $mCode is not a valid callback or has a syntax error.
         */
        public function __construct ($sTrigger, $nLevel, $mCode)
        {
                if ($sTrigger [0] == '/')
                {
                        $this -> m_bIsRegex = true;
                }
                
                $this -> m_sTrigger = $sTrigger;
                $this -> m_nLevel   = $nLevel;
                
                if (!is_callable ($mCode))
                {
                        if (is_array ($mCode))
                        {
                                throw new Exception ('Invalid callback supplied for command "' . $sTrigger . '".');
                        }
                        else
                        {
                                $mCode = create_function ('$sChannel, $sNickname, $sTrigger, $sParams, $aParams', $mCode);
                                if ($mCode === false)
                                {
                                        throw new Exception ('Could not create function from the given code.');
                                }
                        }
                }
                
                $this -> m_cCommand = $mCode;
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
         * @param LVPEchoHandler $pModule A pointer to the main Nuwani module.
         * @param integer $nMode The mode the command is executing in.
         * @param integer $nLevel The level we're operating at.
         * @param string $sChannel The channel we're executing in.
         * @param string $sNickname The nickname we're executed by.
         * @param string $sTrigger The trigger we were triggered by.
         * @param string $sParams All the params in one string.
         * @param array $aParams All the params in an array, split by spaces.
         * @return string
         */
        public function __invoke (LVPEchoHandler $pModule, $nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams)
        {
                if ($this ['Level'] > $nLevel)
                {
                        throw new Exception ("This command can only be executed with level " . $this ['Level'] . " or higher.");
                }
                
                ob_start ();
                
                $mOutput = call_user_func ($this -> m_cCommand, $pModule, $nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams);
                
                if ($mOutput == null)
                {
                        $mOutput = self :: OUTPUT_NORMAL;
                }
                else if (!is_int ($mOutput))
                {
                        $mOutput = self :: OUTPUT_NORMAL . PHP_EOL . trim ($mOutput);
                }
                
                return $mOutput . PHP_EOL . trim (ob_get_clean ());
        }
        
        /**
         * I use the ArrayAccess interface to quickly and painlessly define some
         * getters for the LVPCommand class.
         * 
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         */
        public function offsetExists ($mKey)
        {
                return in_array ($mKey, array ('Trigger', 'UseRegex', 'Level'));
        }
        
        /**
         * I use the ArrayAccess interface to quickly and painlessly define some
         * getters for the LVPCommand class.
         * 
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         */
        public function offsetGet ($mKey)
        {
                switch ($mKey)
                {
                        case 'Trigger':
                        {
                                return $this -> m_sTrigger;
                        }
                        
                        case 'UseRegex':
                        {
                                return $this -> m_bIsRegex;
                        }
                        
                        case 'Level':
                        {
                                return $this -> m_nLevel;
                        }
                }
                
                return null;
        }
        
        /**
         * I use the ArrayAccess interface to quickly and painlessly define some
         * getters for the LVPCommand class.
         * 
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         * @param mixed $mKey The value supplied when setting something.
         */
        public function offsetSet ($mKey, $mValue)
        {
                throw new Exception ("Not supported");
        }
        
        /**
         * I use the ArrayAccess interface to quickly and painlessly define some
         * getters for the LVPCommand class.
         * 
         * @param mixed $mKey The key used when getting something from this class using array syntax.
         */
        public function offsetUnset ($mKey)
        {
                throw new Exception ("Not supported");
        }
}
?>