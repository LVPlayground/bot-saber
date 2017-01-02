<?php
use Nuwani\Database;

/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPWelcomeMessage {
        
        /**
         * @var LVPIrcService
         */
        private $IrcService;

        /**
         * This property holds the welcome messages for every name that has been
         * set up. We do this, so that we don't have to query the database with
         * every single log in on the gameserver.
         * 
         * @var array
         */
        private $m_aWelcomeMessages = array();
        
        /**
         * The constructor will load up the configured welcome messages. This
         * class uses the database as configured in the Nuwani framework itself,
         * not the LVP database.
         * 
         * @param LVPIrcService $ircService
         */
        public function __construct(LVPIrcService $ircService) {
                $this->IrcService = $ircService;
                
                $this->load();
        }
        
        /**
         * This method will simply load up the welcome messages, which are not
         * marked as deleted.
         */
        public function load() {
                $pResult = Database::getInstance()->query(
                        "SELECT
                                nickname, welcome_message
                        FROM
                                samp_welcome_message
                        WHERE
                                is_deleted = 0"
                );
                
                if ($pResult === false) {
                        return;
                }
                
                while (($aRow = $pResult->fetch_assoc()) != null) {
                        $this->m_aWelcomeMessages[$aRow['nickname']] = $aRow['welcome_message'];
                }
        }
        
        /**
         * This method returns a boolean indicating whether there's a welcome
         * message associated with the given nickname.
         * 
         * @param string $sNickname The nickname we want to check.
         * @return boolean
         */
        public function exists($sNickname) {
                return isset($this->m_aWelcomeMessages[$sNickname]);
        }
        
        /**
         * This method adds a new message for the supplied nickname.
         * 
         * @param string $sNickname The nickname we want to add a welcome message to.
         * @param string $sMessage The actual message.
         * @param string $sAddedBy The nickname of the person who added the message.
         * @return boolean
         */
        public function add($sNickname, $sMessage, $sAddedBy) {
                if ($this->exists($sNickname)) {
                        return false;
                }
                
                $this->m_aWelcomeMessages[$sNickname] = $sNewMessage;
                
                $pStatement = Database::getInstance()->prepare(
                        "INSERT INTO samp_welcome_message (
                                nickname, welcome_message, last_edit_by
                        ) VALUES (
                                ?, ?, ?
                        )"
                );
                
                if ($pStatement === false) {
                        return false;
                }
                
                $pStatement->bind_param('sss', $sNickname, $sMessage, $sAddedBy);
                return $pStatement->execute();
        }
        
        /**
         * This method allows us to edit a message with a certain nickname and
         * save it directly to the database.
         * 
         * @param string $sNickname The nickname we want to edit the message from.
         * @param string $sNewMessage The new message.
         * @param string $sEditedBy The nickname of the person who edited the message.
         * @return boolean
         */
        public function edit($sNickname, $sNewMessage, $sEditedBy) {
                if (!$this->exists($sNickname)) {
                        return false;
                }
                
                $this->m_aWelcomeMessages[$sNickname] = $sNewMessage;
                
                $pStatement = Database::getInstance()->prepare(
                        "UPDATE
                                samp_welcome_message
                        SET
                                message = ?,
                                last_edit_by = ?
                        WHERE
                                nickname = ?"
                );
                
                if ($pStatement === false) {
                        return false;
                }
                
                $pStatement->bind_param('sss', $sNewMessage, $sEditedBy, $sNickname);
                return $pStatement->execute();
        }
        
        /**
         * While the welcome messages are a moderately wanted feature, there are
         * people who'd rather not have one. For those, we have the delete
         * command. However, this "delete" only hides the message, in case of
         * deletion abuse.
         * 
         * @param string $sNickname The nickname we want to delete the message from.
         * @param string $sDeletedBy The nickname of the person who deleted the message.
         * @return boolean
         */
        public function delete($sNickname, $sDeletedBy) {
                if (!$this->exists($sNickname)) {
                        return false;
                }
                
                unset($this->m_aWelcomeMessages[$sNickname]);
                
                $pStatement = Database::getInstance()->prepare(
                        "UPDATE
                                samp_welcome_message
                        SET
                                is_deleted = 1,
                                last_edit_by = ?
                        WHERE
                                nickname = ?"
                );
                
                if ($pStatement === false) {
                        return false;
                }
                
                $pStatement->bind_param('ss', $sDeletedBy, $sNickname);
                return $pStatement->execute();
        }
        
        /**
         * Management gets the added bonus of being able to restore deleted
         * messages, in case of abuse. That'll be dealt with in here.
         * 
         * @param string $sNickname The nickname we'll be restoring the message of.
         * @param string $sRestoredBy The nickname of the person who restored it.
         * @return boolean
         */
        public function restore($sNickname, $sRestoredBy) {
                if ($this->exists($sNickname)) {
                        return false;
                }
                
                $pStatement = Database::getInstance()->prepare(
                        "UPDATE
                                samp_welcome_message
                        SET
                                is_deleted = 0,
                                last_edit_by = ?
                        WHERE
                                nickname = ?"
                );
                
                if ($pStatement === false) {
                        return false;
                }
                
                $pStatement->bind_param('ss', $sRestoredBy, $sNickname);
                if (!$pStatement->execute()) {
                        return false;
                }
                
                $pStatement->close();
                
                $this->m_aWelcomeMessages[$sNickname] = '';
                $pStatement = Database::getInstance()->prepare(
                        "SELECT
                                welcome_message
                        FROM
                                samp_welcome_message
                        WHERE
                                nickname = ?"
                );

                if ($pStatement === false) {
                        return false;
                }

                $pStatement->bind_param('s', $sNickname);
                $pStatement->bind_result($this->m_aWelcomeMessages[$sNickname]);
                $pStatement->execute();
                $pStatement->fetch();
                
                return true;
        }
        
        /**
         * This method gets called as soon as someone logs in, so we can
         * check if we need to welcome them with their message or not.
         * 
         * @param integer $nId The ID of the person who just logged in.
         * @param string $sNickname The nickname of the person.
         * @param array $aChunks The complete messsage in chunks.
         */
        public function handleWelcomeMessage($nId, $sNickname, $aChunks) {
                if (!$this->exists($sNickname)) {
                        // No message, no spam.
                        return;
                }
                
                $sMessage = $this->m_aWelcomeMessages[$sNickname];
                $sMessage = str_replace('%id', $nId, $sMessage);
                
                $this->IrcService->privmsg(null, LVP::ECHO_CHANNEL, '!pm ' . $nId . ' ' . $sMessage);
        }
}
