<?php
/**
 * LVPEchoHandler module for Nuwani v2
 *
 * @author Xander Hoogland <home@xanland.nl>
 *
 * LVPRadioHandler.php 2016-07-22 21:17:00Z Xander
 */
class LVPRadioHandler extends LVPEchoHandlerClass
{
    /**
     * Keeps track of the autodj is currently running.
     *
     * @var bool
     */
    private $m_isAutoDjRunning = true;

    /**
     * In all cases, we would like to know who is now DJing. Whether be it the autodj or a player.
     *
     * @var string
     */
    private $m_currentDjName = 'LVP_Radio';

    /**
     * The command players use to let the autodj stop running.
     */
    const STOPAUTODJ_COMMAND_TRIGGER = '!stopautodj';

    /**
     * The constructor will call the parent constructor. Besides that it initiates the command we
     * are going to need so players can go to dj.
     *
     * @param LVPEchoHandler $pEchoHandler The LVPEchoHandler module we're residing in.
      */
    public function __construct (LVPEchoHandler $pEchoHandler)
    {
        parent:: __construct ($pEchoHandler);

        $this -> registerCommands ();
    }

    /**
     * This method will register the commands this class handles to the
     * LVPCommandHandler.
     */
    private function registerCommands ()
    {
        $this -> m_pModule ['Commands'] -> register (new LVPCommand (self::STOPAUTODJ_COMMAND_TRIGGER, LVP::LEVEL_NONE, array ($this, 'handleStopAutoDj')));
    }

    /**
     * @param $pModule
     * @param $nMode
     * @param $nLevel
     * @param $sChannel
     * @param $sNickname
     * @param $sTrigger
     * @param $sParams
     * @param $aParams
     *
     * @return int
     */
    public function handleStopAutoDj ($pModule, $nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams)
    {
        return;

        if ($sTrigger == self::STOPAUTODJ_COMMAND_TRIGGER
            && $nMode == LVPCommand::MODE_IRC
            && strtolower ($sChannel) == '#lvp.radio')
        {
            if ($this -> m_isAutoDjRunning == true)
            {
                $pBot = $this -> m_pModule -> getBot ($sChannel);
                $pBot -> send ('PRIVMSG LVP_Radio :!autodj-force');
            }
            else
            {
                echo 'The autoDJ is not streaming. Ask the current DJ to stop streaming.';
            }

            return LVPCommand :: OUTPUT_NORMAL;
        }
    }

    /**
     * Called from Module.php when the user's hostname matches the hostname of LVP_Radio. We want to
     * check the message for who is DJing.
     *
     * @param string $sMessage The line written the channel to look up who is DJin, if present.
     */
    public function processChannelMessage ($sMessage)
    {
        return;

        if (!$this -> didCheckForDjDetailsWithResult ($sMessage, ' is off --> Coming up: '))
        {
            if (!$this -> didCheckForDjDetailsWithResult ($sMessage, '[LVP Radio] Current DJ: '))
            {
                $this -> didCheckForDjDetailsWithResult ($sMessage, 'The current DJ is: ', false);
            }
        }
    }

    /**
     * We need to check for the line for a few specific given words who is DJing. Here we process
     * the given line with strpos and substr to determine if it is a correct line and if it matches
     * who is DJing.
     *
     * @param      $sMessage
     * @param      $searchString
     * @param bool $omitLastCharacter
     *
     * @return bool Whether there is a match or not.
     */
    private function didCheckForDjDetailsWithResult ($sMessage, $searchString, $omitLastCharacter = true)
    {
        $startMessageLength = strpos ($sMessage, $searchString);
        if ($startMessageLength !== false)
        {
            $this -> m_currentDjName = substr ($sMessage, $startMessageLength + strlen($searchString), $omitLastCharacter ? -1 : 0);
            $this -> m_isAutoDjRunning = $this -> m_currentDjName == 'LVP_Radio';
            return true;
        }
        return false;
    }
}
