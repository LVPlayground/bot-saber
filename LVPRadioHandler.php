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
     * We totally don't know if it is running or not at the start-up.
     *
     * @var null
     */
    private $m_isAutoDjRunning = null;

    /**
     * We totally don't know who is dj'ing when starting up.
     *
     * @var null
     */
    private $m_currentDjName = null;

    /**
     *
     */
    private const STOPAUTODJ_COMMAND_TRIGGER = '!stopautodj';

    /**
     * The constructor will call the parent constructor. Besides that it initiates the command we
     * are going to need and it set a off-value for the current (auto)dj-state.
     *
     * @param LVPEchoHandler $pEchoHandler The LVPEchoHandler module we're residing in.
      */
    public function __construct (LVPEchoHandler $pEchoHandler)
    {
        parent:: __construct ($pEchoHandler);

        //$this->registerCommands ();
    }

    /**
     * This method will register the commands this class handles to the
     * LVPCommandHandler.
     */
    private function registerCommands ()
    {
        $this -> m_pModule ['Commands'] -> register (new LVPCommand (self::STOPAUTODJ_COMMAND_TRIGGER, LVP::LEVEL_NONE, array ($this, 'handleCommands')));
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

        if ($nMode == LVPCommand::MODE_IRC
            && $sTrigger == self::STOPAUTODJ_COMMAND_TRIGGER
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
     * @param $sMessage
     */
    public function processChannelMessage ($sMessage)
    {
        return;
        
        $this -> checkForDjDetails ($sMessage, ' is off --> Coming up: ');
        $this -> checkForDjDetails ($sMessage, '[LVP Radio] Current DJ: ');
    }

    /**
     * @param $sMessage
     * @param $searchString
     */
    private function checkForDjDetails ($sMessage, $searchString)
    {
        $messageStartPosition = strpos ($sMessage, $searchString);
        if ($messageStartPosition !== false)
        {
            $this -> m_currentDjName = substr ($sMessage, $messageStartPosition + strlen($searchString), -1);
            $this -> m_isAutoDjRunning = $this -> m_currentDjName == 'LVP_Radio';
        }
    }
}
