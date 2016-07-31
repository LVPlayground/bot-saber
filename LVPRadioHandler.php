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
     * Name of the irc-bot who provides the radio-information.
     */
    const RADIO_BOT_NAME = 'lvp_radio';

    /**
     * Name of the DJ when the autodj is active.
     */
    const AUTO_DJ_NAME = 'lvp_radio';

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
    public function __construct(LVPEchoHandler $pEchoHandler) {
        parent::__construct($pEchoHandler);

        $this->registerCommands();
    }

    /**
     * This method will register the commands this class handles to the
     * LVPCommandHandler.
     */
    private function registerCommands() {
        $this->m_pModule['Commands']->register(new LVPCommand(self::STOPAUTODJ_COMMAND_TRIGGER, LVP::LEVEL_NONE, array ($this, 'handleStopAutoDj')));
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
    public function handleStopAutoDj ($pModule, $nMode, $nLevel, $sChannel, $sNickname, $sTrigger, $sParams, $aParams) {
        if (strtolower($sTrigger) == self::STOPAUTODJ_COMMAND_TRIGGER
        && $nMode == LVPCommand::MODE_IRC
        && strtolower($sChannel) == LVP::RADIO_CHANNEL) {
            $this->m_isStopAutoDjCommandExecuting = true;
            $this->m_nicknameOfPlayerExecutingStopAutoDjCommand = $sNickname;

            $pBot = $this->m_pModule->getBot($sChannel);
            $pBot->send('NAMES ' . LVP::RADIO_CHANNEL);


        }

        return;
    }

    /**
     * At telling the radio-bot to stop the autodj, it returns a message whether it successfully
     * stopped it or not. We need to act on this towards the executing user.
     *
     * @param string $message Message received from the radiobot
     */
    public function processPrivateMessage($message) {
        if ($message == 'Stopping immediately...') {
            $pBot = $this->m_pModule->getBot(LVP::RADIO_CHANNEL);
            $this->m_pModule->info($pBot, LVP::RADIO_CHANNEL,
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
    public function handleNamesChecking(array $names) {
        if (!$this -> m_isStopAutoDjCommandExecuting) {
            return;
        }

        foreach ($names as $rightsWithNickname) {
            if (strpos ($rightsWithNickname, $this -> m_nicknameOfPlayerExecutingStopAutoDjCommand) === false) {
                continue;
            }

            $pBot = $this->m_pModule->getBot(LVP::RADIO_CHANNEL);
            if (in_array($rightsWithNickname[0], array('+', '%', '@', '&', '~'))) {
                if ($this->m_isAutoDjRunning == true) {
                    $this->m_pModule->privmsg($pBot, self::RADIO_BOT_NAME, '!autodj-force');
                } else {
                    $this->m_pModule->notice($pBot, LVP::RADIO_CHANNEL,
                        'The autoDJ is not streaming. Please ask ' . $this -> m_currentDjName . ' to stop streaming.',
                        LVPCommand::MODE_IRC);
                }
            } else {
                $this->m_pModule->notice($pBot, LVP::RADIO_CHANNEL,
                    'Only available for voiced users and above', LVPCommand::MODE_IRC);
            }
        }

        $this->m_isStopAutoDjCommandExecuting = false;
        $this->m_nicknameOfPlayerExecutingStopAutoDjCommand = null;
    }

    /**
     * Called from Module.php when the user's hostname matches the hostname of the radiobot. We
     * want to check the message for who is DJing.
     *
     * @param string $message The line written the channel to look up who is DJin, if present.
     */
    public function processChannelMessage ($message) {
        if (!$this->storeDjName($message, ' is off --> Coming up: ')) {
            if (!$this->storeDjName($message, '[LVP Radio] Current DJ: ')) {
                $this->storeDjName($message, 'The current DJ is: ', false);
            }
        }
    }

    /**
     * This method looks for the name of the current DJ in the given incoming
     * message.
     *
     * @param string $incomingMessage The message to process.
     * @param string $searchString The string after which the name of the DJ can be found.
     * @param bool $omitLastCharacter Whether the last character of the name of the DJ should be omitted.
     *
     * @return bool Whether the name of the DJ was found.
     */
    private function storeDjName($incomingMessage, $searchString, $omitLastCharacter = true) {
        $searchPos = strpos($incomingMessage, $searchString);
        if ($searchPos !== false) {
            $djName = substr($incomingMessage, $searchPos + strlen($searchString));

            if ($omitLastCharacter) {
                $djName = substr($djName, -1);
            }

            $this->m_currentDjName = $djName;
            $this->m_isAutoDjRunning = strtolower($this->m_currentDjName) == self::AUTO_DJ_NAME;
            return true;
        }

        return false;
    }
}
