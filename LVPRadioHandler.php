<?php
/**
 * LVPEchoHandler module for Nuwani v2
 *
 * @author Xander Hoogland <home@xanland.nl>
 */
class LVPRadioHandler extends LVPEchoHandlerClass
{
    /**
     * The command players use to let the autodj stop running.
     */
    const STOPAUTODJ_COMMAND_TRIGGER = '!stopautodj';

    /**
     * Keeps track of whether the autodj is currently running.
     *
     * @var bool
     */
    private $m_isAutoDjRunning = true;

    /**
     * In all cases, we would like to know who is now DJing. Whether be it the autodj or a player.
     *
     * @var string
     */
    private $m_currentDjName = null;

    /**
     * The name of the only channel where this handler is valid in.
     */
    private $m_lvpRadioChannelName = '#lvp.radio';

    /**
     * Name of the irc-bot who provides the radio-information.
     *
     * @var string
     */
    private $m_radioBotName = 'lvp_radio';

    /**
     * Name of the DJ when the autodj is active.
     *
     * @var string
     */
    private $m_autoDjName = 'lvp_radio';

    /**
     * To determine the rights of the executing user, we send a names command. With using this
     * field on the right place we ensure proper work of the command.
     *
     * @var bool
     */
    private $m_isStopAutoDjCommandExecuting = false;

    /**
     * Since the stopautodj is performed in two different methods which don't call each other,
     * we need to keep track of the username in a field.
     *
     * @var null
     */
    private $m_nicknameOfPlayerExecutingStopAutoDjCommand = null;

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
     * Here we handle the !stopautodj-cmd. If all the requirements are correct the radiobot should
     * send the names-command for the radio-channel. On the place where we receive it we actually
     * process what this command should do.
     *
     * @param $pModule
     * @param int $nMode Where this command is executed: adminchat, mainchat or just on irc
     * @param $nLevel
     * @param string $sChannel Channel the command was executed in.
     * @param string $sNickname The name of the player who executes this command
     * @param string $sTrigger First word including ! where it should act on
     * @param $sParams
     * @param $aParams
     *
     * @return int|void
     */
    public function handleStopAutoDj ($pModule, $nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams)
    {
        if (strtolower ($sTrigger) == self::STOPAUTODJ_COMMAND_TRIGGER
            && $nMode == LVPCommand::MODE_IRC
            && strtolower ($sChannel) == $this -> m_lvpRadioChannelName)
        {
            $this -> m_isStopAutoDjCommandExecuting = true;
            $this -> m_nicknameOfPlayerExecutingStopAutoDjCommand = $sNickname;

            $pBot = $this -> m_pModule -> getBot ($sChannel);
            $pBot -> send ('NAMES ' . $this -> m_lvpRadioChannelName);


        }

        return;
    }

    /**
     * At telling the radio-bot to stop the autodj, it returns a message whether it successfully
     * stopped it or not. We need to act on this towards the executing user.
     *
     * @param string $message Message received from the radiobot
     */
    public function processPrivateMessage ($message)
    {
        if ($message == 'Stopping immediately...')
        {
            $pBot = $this -> m_pModule -> getBot ($this -> m_lvpRadioChannelName);
            $this -> m_pModule -> info ($pBot, $this -> m_lvpRadioChannelName,
                'The autodj is stopping and will reconnect within 60 seconds. Start DJ\'ing now!',
                LVPCommand::MODE_IRC);
        }

        return;
    }

    /**
     * Is received after a player executes the !stopautodj-command. It checks if the user has the
     * correct rights to be able to use the command.
     *
     * @param array $names Names of the players with the rights in the defined and executed channel
     */
    public function handleNamesChecking(array $names)
    {
        if ($this -> m_isStopAutoDjCommandExecuting)
        {
            foreach ($names as $rightsWithNickname)
            {
                if (strpos ($rightsWithNickname, $this -> m_nicknameOfPlayerExecutingStopAutoDjCommand) !== false)
                {
                    $pBot = $this -> m_pModule -> getBot ($this -> m_lvpRadioChannelName);
                    if (in_array($rightsWithNickname[0], array ('+', '%', '@', '&', '~')))
                    {
                        if ($this -> m_isAutoDjRunning == true)
                            $pBot -> send ('PRIVMSG ' . $this -> m_radioBotName . ' :!autodj-force');
                        else
                        {
                            $this -> m_pModule -> error ($pBot, $this -> m_lvpRadioChannelName,
                                'The autoDJ is not streaming. Please ask ' . $this -> m_currentDjName . ' to stop streaming.',
                                LVPCommand::MODE_IRC);
                        }
                    }
                    else
                    {
                        $this -> m_pModule -> error ($pBot, $this -> m_lvpRadioChannelName,
                            'Only available for voiced users and above', LVPCommand::MODE_IRC);
                    }

                    break;
                }
            }
        }

        $this -> m_isStopAutoDjCommandExecuting = false;
        $this -> m_nicknameOfPlayerExecutingStopAutoDjCommand = null;
    }

    /**
     * Called from Module.php when the user's hostname matches the hostname of the radiobot. We
     * want to check the message for who is DJing.
     *
     * @param string $sMessage The line written the channel to look up who is DJin, if present.
     */
    public function processChannelMessage ($sMessage)
    {
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
     * @param string $sMessage
     * @param string $searchString
     * @param bool $omitLastCharacter
     *
     * @return bool Whether there is a match or not.
     */
    private function didCheckForDjDetailsWithResult ($sMessage, $searchString, $omitLastCharacter = true)
    {
        $startMessageLength = strpos ($sMessage, $searchString);
        if ($startMessageLength !== false)
        {
            $djName = substr ($sMessage, $startMessageLength + strlen ($searchString), -1);
            if (!$omitLastCharacter)
                $djName = substr ($sMessage, $startMessageLength + strlen ($searchString));

            $this -> m_currentDjName = $djName;
            $this -> m_isAutoDjRunning = strtolower ($this -> m_currentDjName) == $this -> m_autoDjName;
            return true;
        }
        return false;
    }

    /**
     * Gives the name of the channel where this command can only be handled in.
     *
     * @return string Name of the channel
     */
    public function getLvpRadioChannelName()
    {
        return $this -> m_lvpRadioChannelName;
    }

    /**
     * Gives the name of the bot who provides us the messages in the radio-channel.
     *
     * @return string Name of the bot
     */
    public function getRadioBotName()
    {
        return $this -> m_radioBotName;
    }
}
