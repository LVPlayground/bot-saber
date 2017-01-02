<?php
/**
 * LVPEchoHandler module for Nuwani v2
 *
 * @author Xander Hoogland <home@xanland.nl>
 */
class LVPRadioHandler extends LVPEchoHandlerClass {
    
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
    private $isAutoDjRunning = true;

    /**
     * In all cases, we would like to know who is now DJing. Whether be it the autodj or a player.
     *
     * @var string
     */
    private $currentDjName = null;

    /**
     * To determine the rights of the executing user, we send a names command. With using this
     * field on the right place we ensure proper work of the command.
     *
     * @var bool
     */
    private $isStoppingAutoDj = false;

    /**
     * Since the stopautodj is performed in two different methods which don't call each other,
     * we need to keep track of the username in a field.
     *
     * @var null
     */
    private $autoDjStoppedBy = null;

    /**
     * The constructor will call the parent constructor. Besides that it initiates the command we
     * are going to need so players can go to dj.
     *
     * @param LVPEchoHandler $pEchoHandler The LVPEchoHandler module we're residing in.
      */
    public function __construct(LVPEchoHandler $pEchoHandler) {
        parent::__construct($pEchoHandler);
    }

    /**
     * At telling the radio-bot to stop the autodj, it returns a message whether it successfully
     * stopped it or not. We need to act on this towards the executing user.
     *
     * @param string $message Message received from the radiobot
     */
    public function processPrivateMessage($message) {
        if ($message == 'Stopping immediately...') {
            $pBot = $this->IrcService->getBot(LVP::RADIO_CHANNEL);
            $this->IrcService->info($pBot, LVP::RADIO_CHANNEL,
                'The auto DJ is stopping and will reconnect after 60 seconds. Start DJ\'ing now!',
                LVPCommand::MODE_IRC);
        }
    }

    /**
     * Is received after a player executes the !stopautodj-command. It checks if the user has the
     * correct rights to be able to use the command.
     *
     * @param array $names Names of the players with the rights in the defined and executed channel
     */
    public function handleNamesChecking(array $names) {
        if (!$this->isStoppingAutoDj) {
            return;
        }

        foreach ($names as $rightsWithNickname) {
            if (strpos ($rightsWithNickname, $this->autoDjStoppedBy) === false) {
                continue;
            }

            $pBot = $this->IrcService->getBot(LVP::RADIO_CHANNEL);
            if (in_array($rightsWithNickname[0], array('+', '%', '@', '&', '~'))) {
                if ($this->isAutoDjRunning == true) {
                    $this->IrcService->privmsg($pBot, self::RADIO_BOT_NAME, '!autodj-force');
                } else {
                    $this->IrcService->notice($pBot, LVP::RADIO_CHANNEL,
                        'The auto DJ is not streaming. Please ask ' . $this->currentDjName . ' to stop streaming.',
                        LVPCommand::MODE_IRC);
                }
            } else {
                $this->IrcService->notice($pBot, LVP::RADIO_CHANNEL,
                    'Only available for users with at least voice rights.', LVPCommand::MODE_IRC);
            }
        }

        $this->isStoppingAutoDj = false;
        $this->autoDjStoppedBy = null;
    }

    /**
     * Called from Module.php when the user's hostname matches the hostname of the radiobot. We
     * want to check the message for who is DJing.
     *
     * @param string $nickname The nickname of the person who wrote the line.
     * @param string $message The line written the channel to look up who is DJin, if present.
     */
    public function processChannelMessage($nickname, $message) {
        if (strtolower($nickname) == LVPRadioHandler::RADIO_BOT_NAME) {
            return $this->storeDjName($message, ' is off --> Coming up: ') ||
                $this->storeDjName($message, '[LVP Radio] Current DJ: ') ||
                $this->storeDjName($message, 'The current DJ is: ', false);
        } else {
            list($trigger, $params, $splitParams) = Util::parseMessage($message);

            // Here we handle the !stopautodj-cmd. If all the requirements are correct the radiobot should
            // send the names-command for the radio-channel. On the place where we receive it we actually
            // process what this command should do.
            // TODO Future improvement: use the ChannelTracking class.
            if ($trigger == self::STOPAUTODJ_COMMAND_TRIGGER) {
                $this->isStoppingAutoDj = true;
                $this->autoDjStoppedBy = $nickname;

                $bot = $this->IrcService->getBot(LVP::RADIO_CHANNEL);
                $bot->send('NAMES ' . LVP::RADIO_CHANNEL);
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
                $djName = substr($djName, 0, -1);
            }

            $this->currentDjName = $djName;
            $this->isAutoDjRunning = strtolower($this->currentDjName) == self::AUTO_DJ_NAME;
            return true;
        }

        return false;
    }
}
