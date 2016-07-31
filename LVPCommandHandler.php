<?php
require 'LVPCommand.php';

use Nuwani \ Bot;

/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPCommandHandler extends LVPEchoHandlerClass
{
        
        /**
         * An array with commands indexed by their plain text triggers.
         * 
         * @var array
         */
        private $m_aTriggerCommands = array ();
        
        /**
         * An array with commands indexed by the regular expressions that
         * trigger the command when matched. I seperated this for optimum speed.
         * 
         * @var array
         */
        private $m_aRegexCommands = array ();
        
        /**
         * This method allows one to register a new LVPCommand with the handler.
         * 
         * @param LVPCommand $pCommand The command to register to this class.
         */
        public function register (LVPCommand $pCommand)
        {
                $this -> unregister ($pCommand);
                
                if ($pCommand ['UseRegex'])
                {
                        $aCommands = & $this -> m_aRegexCommands;
                }
                else
                {
                        $aCommands = & $this -> m_aTriggerCommands;
                }
                
                $aCommands [$pCommand ['Trigger']] = $pCommand;
        }
        
        /**
         * This method allows one to unregister an LVPCommand from this class.
         * 
         * @param LVPCommand $pCommand The command to unregister from this class.
         */
        public function unregister (LVPCommand $pCommand)
        {
                if ($pCommand ['UseRegex'])
                {
                        $aCommands = & $this -> m_aRegexCommands;
                }
                else
                {
                        $aCommands = & $this -> m_aTriggerCommands;
                }
                
                if (isset ($aCommands [$pCommand ['Trigger']]))
                {
                        unset ($aCommands [$pCommand ['Trigger']]);
                }
        }
        
        /**
         * All LVP related messages will pass through here, checked if there's
         * a command in there, and if so, the command will be executed depending
         * on the input.
         * 
         * @param Bot $pBot The bot that received the message.
         * @param string $sChannel The channel we received this message in.
         * @param string $sNickname The nickname from which we got this message.
         * @param string $sMessage And of course the message itself.
         */
        public function handle (Bot $pBot, $sChannel, $sNickname, $sMessage)
        {
                if ($sMessage [0] != '!')
                {
                        return ;
                }
                
                list ($sTrigger, $sParams, $aParams) = Func :: parseMessage ($sMessage);
                
                $nLevel = $this -> m_pModule -> getChannelLevel ($sChannel);
                
                try
                {
                        $sOutput = $this -> handleCommands (LVPCommand :: MODE_IRC, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams);
                        $this -> processOutput ($pBot, $sChannel, $sOutput, LVPCommand :: MODE_IRC);
                }
                catch (Exception $pException) {
                        echo 'Exception while executing command ' . $sTrigger . ': ' . $pException->getMessage();
                }
        }
        
        /**
         * When a command request has been detected in the crew chat, it will be
         * passed through this method. This does effectively the same the
         * handle () method, except that it returns the output in a command back
         * to the LVP gamemode via the crew channel.
         * 
         * @param integer $nLevel The level we're operating at.
         * @param string $sNickname The nickname that triggered the command.
         * @param string $sTrigger The first word of the message.
         * @param array $aParams The rest of the message seperated by whitespaces.
         */
        public function handleCrewChat ($nLevel, $sNickname, $sTrigger, $aParams)
        {
                try
                {
                        $sOutput = $this -> handleCommands (LVPCommand :: MODE_CREW_CHAT, $nLevel, LVP :: ECHO_CHANNEL, $sNickname, $sTrigger, implode (' ', $aParams), $aParams);
                        $this -> processOutput (null, LVP :: CREW_CHANNEL, $sOutput, LVPCommand :: MODE_CREW_CHAT);
                }
                catch (Exception $pException) {
                        echo 'Exception while executing command ' . $sTrigger . ' in crew chat: ' . $pException->getMessage();
                }
        }
        
        /**
         * This module supports commands in the main chat as well. The level is
         * variable, so that crew only commands can be triggered in the main
         * chat as well.
         * 
         * @param integer $nLevel The level we're operating at.
         * @param string $sNickname The nickname that triggered the command.
         * @param string $sTrigger The first word of the message.
         * @param array $aParams The rest of the message seperated by whitespaces.
         */
        public function handleMainChat ($nLevel, $sNickname, $sTrigger, $aParams)
        {
                try
                {
                        $sOutput = $this -> handleCommands (LVPCommand :: MODE_MAIN_CHAT, $nLevel, LVP :: ECHO_CHANNEL, $sNickname, $sTrigger, implode (' ', $aParams), $aParams);
                        $this -> processOutput (null, LVP :: ECHO_CHANNEL, $sOutput, LVPCommand :: MODE_MAIN_CHAT);
                }
                catch (Exception $pException) {
                        echo 'Exception while executing command ' . $sTrigger . ' in main chat: ' . $pException->getMessage();
                }
        }
        
        /**
         * All and every command execution request will pass through this
         * method. It will check both the regex command as well as the trigger
         * command arrays if there's a command to be found. If there is, it will
         * be executed with some extra parameters which can be used in the
         * command itself. This method returns all output from those commands,
         * either returned or fetched using output buffers.
         * 
         * @param integer $nMode The mode the command is executing in.
         * @param integer $nLevel The level we're currently operating at.
         * @param string $sChannel The channel to send the output to.
         * @param string $sNickname The nickname that triggered the command.
         * @param string $sTrigger The trigger used.
         * @param string $sParams The rest of the message after the trigger.
         * @param array $aParams The rest of the message in chunks.
         */
        private function handleCommands ($nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams)
        {
                if (isset ($this -> m_aTriggerCommands [$sTrigger]))
                {
                        return $this -> m_aTriggerCommands [$sTrigger] ($this -> m_pModule, $nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams);
                }
                
                // Still here, so that means there was no trigger command. On to
                // the regular expression commands, which we'll (unfortunately)
                // have to loop through.
                foreach ($this -> m_aRegexCommands as $sRegex => $pCommand)
                {
                        if (preg_match ($sRegex, $sTrigger))
                        {
                                return $pCommand ($this -> m_pModule, $nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams);
                        }
                }
        }
        
        /**
         * This method will take the raw output from a command, split it by the
         * line endings and send them one by one to IRC. In future message
         * throttling can be added. Probably.
         * 
         * @param Bot $pBot The bot we're using to send our output.
         * @param string $sChannel The channel to send our output to.
         * @param string $sOutput The actual output of a command.
         * @param integer $nMode The mode the output should be processed at.
         */
        public function processOutput ($pBot, $sChannel, $sOutput, $nMode = LVPCommand :: MODE_IRC)
        {
                if ($sOutput == null || $sOutput == '')
                {
                        return ;
                }
                
                if ($pBot == null)
                {
                        $pBot = $this -> m_pModule -> getBot ($sChannel);
                }
                
                list ($nReturnCode, $sOutput) = explode (PHP_EOL, $sOutput, 2);
                
                foreach (explode ("\n", $sOutput) as $sLine)
                {
                        $sLine = trim ($sLine);
                        if (strlen ($sLine) == 0) continue;
                        
                        switch ($nReturnCode)
                        {
                                default:
                                case LVPCommand :: OUTPUT_NORMAL:  $sCallback = 'privmsg'; break;
                                case LVPCommand :: OUTPUT_ERROR:   $sCallback = 'error';   break;
                                case LVPCommand :: OUTPUT_INFO:    $sCallback = 'info';    break;
                                case LVPCommand :: OUTPUT_NOTICE:  $sCallback = 'notice';  break;
                                case LVPCommand :: OUTPUT_SUCCESS: $sCallback = 'success'; break;
                                case LVPCommand :: OUTPUT_USAGE:   $sCallback = 'usage';   break;
                        }
                        
                        call_user_func (array ($this -> m_pModule, $sCallback), $pBot, $sChannel, $sLine, $nMode);
                }
        }
}
?>
