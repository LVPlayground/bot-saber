<?php
/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * @author Dik Grapendaal <dik.grapendaal@gmail.com>
 * 
 * $Id: LVPTrac.php 187 2010-11-27 02:40:21Z Dik $
 */
class LVPTrac extends LVPEchoHandlerClass
{
        
        /**
         * The file where the cookies of the Trac will be stored.
         * 
         * @var string
         */
        const   COOKIEFILE              = 'Data/LVP/Trac_Cookies.txt';
        
        /**
         * The timestamp of when we logged in.
         * 
         * @var integer
         */
        private $m_nLoggedIn = 0;
        
        /**
         * 
         */
        private $m_aProgressInfo;
        
        /**
         * The constructor will call the parent constructor and prepare some
         * extra stuff in this class as an added bonus.
         * 
         * @param LVPEchoHandler $pEchoHandler The LVPEchoHandler module we're residing in.
         */
        public function __construct (LVPEchoHandler $pEchoHandler)
        {
                parent :: __construct ($pEchoHandler);
                
                $this -> loadProgressInfo ();
                $this -> registerCommands ();
        }
        
        public function __destruct ()
        {
                $this -> logout ();
        }
        
        public function loadProgressInfo ()
        {
                $sDataFile = 'Data/LVP/TracProgress.dat';
                if (file_exists ($sDataFile))
                {
                        $this -> m_aProgressInfo = unserialize (file_get_contents ($sDataFile));
                }
        }
        
        public function saveProgressInfo ()
        {
                file_put_contents ('Data/LVP/TracProgress.dat', serialize ($this -> m_aProgressInfo));
        }
        
        /**
         * This method will register the commands this class handles to the
         * LVPCommandHandler.
         */
        private function registerCommands ()
        {
                $aCommands = array
                (
                        '!progress'     => LVP :: LEVEL_NONE,
                        '!lvpmilestone' => LVP :: LEVEL_DEVELOPER
                );
                
                foreach ($aCommands as $sTrigger => $nLevel)
                {
                        $this -> m_pModule ['Commands'] -> register (new LVPCommand ($sTrigger, $nLevel, array ($this, 'handleCommands')));
                }
        }
        
        /**
         * This method will log us in into the LVP Trac. This will create a
         * session, so that we can retrieve data. Exciting.
         * 
         * @return boolean Indicates whether we successfully logged in.
         */
        private function login ()
        {
                touch (self :: COOKIEFILE);
                
                $pReader = curl_init (LVP :: TRAC_BASE_URL . '/login');
                curl_setopt ($pReader, CURLOPT_RETURNTRANSFER, true);
                curl_setopt ($pReader, CURLOPT_COOKIEJAR,  self :: COOKIEFILE);
                curl_setopt ($pReader, CURLOPT_COOKIEFILE, self :: COOKIEFILE);
                curl_setopt ($pReader, CURLOPT_TIMEOUT_MS, 2000);
                $sOutput = curl_exec ($pReader);
                curl_close ($pReader);
                
                preg_match ('/__FORM_TOKEN" value="([^\"]+)"/s', $sOutput, $aMatches);
                if (!count ($aMatches))
                {
                        return false;
                }
                
                $pReader = curl_init (LVP :: TRAC_BASE_URL . '/login');
                curl_setopt ($pReader, CURLOPT_RETURNTRANSFER, true);
                curl_setopt ($pReader, CURLOPT_COOKIEJAR,  self :: COOKIEFILE);
                curl_setopt ($pReader, CURLOPT_COOKIEFILE, self :: COOKIEFILE);
                curl_setopt ($pReader, CURLOPT_TIMEOUT_MS, 2000);
                curl_setopt ($pReader, CURLOPT_POST, true);
                curl_setopt ($pReader, CURLOPT_POSTFIELDS, http_build_query (array
                (
                        '__FORM_TOKEN'  => $aMatches [1],
                        'user'          => LVP :: TRAC_USERNAME,
                        'password'      => LVP :: TRAC_PASSWORD,
                        'referer'       => ''
                )));
                
                $sOutput = curl_exec ($pReader);
                curl_close ($pReader);
                
                $this -> m_nLoggedIn = time ();
                
                return true;
        }
        
        /**
         * Logs us out of the Trac.
         */
        private function logout ()
        {
                $this -> request ('/logout');
                
                unlink (self :: COOKIEFILE);
        }
        
        /**
         * Requests a page from the Trac and returns the contents.
         * 
         * @param string $sUrl The relative URL to retrieve.
         * @return string
         */
        private function request ($sUrl)
        {
                if (time () - $this -> m_nLoggedIn > 86400)
                {
                        /** Log in (again). **/
                        if (!$this -> login ())
                        {
                                throw new Exception ('Could not login to the Trac.');
                        }
                }
                
                $pReader = curl_init (LVP :: TRAC_BASE_URL . $sUrl);
                curl_setopt ($pReader, CURLOPT_RETURNTRANSFER, true);
                curl_setopt ($pReader, CURLOPT_COOKIEJAR,  self :: COOKIEFILE);
                curl_setopt ($pReader, CURLOPT_COOKIEFILE, self :: COOKIEFILE);
                curl_setopt ($pReader, CURLOPT_TIMEOUT_MS, 2000);
                $sOutput = curl_exec ($pReader);
                curl_close ($pReader);
                
                return $sOutput;
        }
        
        /**
         * This method deals with processing the command input from IRC. It
         * determines what needs to be done according to the trigger and the
         * given parameters. It then uses the methods in this class to gather
         * the requested information and outputs a message understandable for
         * human beings with the information they need.
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
                        case '!progress':       { return $this -> handleProgress ();                    }
                        case '!lvpmilestone':   { return $this -> handleSetMilestone ($sParams);        }
                }
        }
        
        /**
         * Handles the !progress command, which is a public command that shows
         * the current progression towards the set milestone in a percentage.
         */
        private function handleProgress ()
        {
                if ($this -> m_aProgressInfo ['Milestone'] == '') return LVPCommand :: OUTPUT_NORMAL;
                
                $sTicketsCsv = $this -> request ('/query?group=status&format=csv&milestone=' . urlencode ($this -> m_aProgressInfo ['Milestone']) .
                        '&order=priority&col=id&col=summary&col=owner&col=type&col=status&col=priority&col=version&col=resolution');
                
                $nOpenTickets = 0;
                foreach (explode ("\n", $sTicketsCsv) as $sLine)
                {
                        $aChunks = str_getcsv (trim ($sLine));
                        
                        if (!isset ($aChunks [4]))
                        {
                                continue;
                        }
                        
                        if (in_array ($aChunks [4], array ('new', 'accepted', 'assigned')))
                        {
                                $nOpenTickets ++;
                        }
                }
                
                $nPercentageLeft = 100 - $this -> m_aProgressInfo ['LastProgress'];
                $nTicketDiff = $this -> m_aProgressInfo ['OpenTickets'] - $nOpenTickets;
                
                if ($this -> m_aProgressInfo ['OpenTickets'] == 0)
                {
                        $nPercentagePart = 100;
                }
                else
                {
                        $nPercentagePart = $nPercentageLeft / $this -> m_aProgressInfo ['OpenTickets'];
                }
                
                $nProgress = max (0, $nPercentagePart * $nTicketDiff);
                $nProgress += $this -> m_aProgressInfo ['LastProgress'];
                
                echo ModuleBase :: COLOUR_TEAL . '* ' . $this -> m_aProgressInfo ['Milestone'] . ' progress' . ModuleBase :: CLEAR . ': ';
                echo $nProgress . '%';
                
                if ($this -> m_aProgressInfo ['DueDate'] !== null)
                {
                        $nTimeLeft = $this -> m_aProgressInfo ['DueDate'] - time ();
                        
                        if ($nTimeLeft > -86400)
                        {
                                echo ' - Due ';
                                if ($nTimeLeft <= 0)
                                {
                                        echo 'today!';
                                }
                                else
                                {
                                        echo 'in ' . Util :: formatTime ($nTimeLeft);
                                }
                        }
                }
                
                $this -> m_aProgressInfo ['LastProgress'] = $nProgress;
                $this -> m_aProgressInfo ['OpenTickets'] = $nOpenTickets;
                
                $this -> saveProgressInfo ();
                
                return LVPCommand :: OUTPUT_NORMAL;
        }
        
        /**
         * This method handles the !setmilestone command, which allows LVP 
         * developers to update the current milestone from which we need to
         * retrieve the current progress information.
         * 
         * @param string $sMilestone The milestone to set.
         */
        private function handleSetMilestone ($sMilestone)
        {
                if ($sMilestone == null)
                {
                        echo '!lvpmilestone MilestoneName';
                        return LVPCommand :: OUTPUT_USAGE;
                }
                
                /** Check milestone validity. **/
                $sPage = $this -> request ('/milestone/' . rawurlencode ($sMilestone));
                if (strpos ($sPage, 'Error: Invalid Milestone Name') !== false)
                {
                        echo 'Invalid milestone name.';
                        return LVPCommand :: OUTPUT_ERROR;
                }
                
                $this -> m_aProgressInfo = array
                (
                        'Milestone'     => $sMilestone,
                        'DueDate'       => null,
                        'OpenTickets'   => 0,
                        'LastProgress'  => 0
                );
                
                if (preg_match ('/<p class="date">.+title="(.+?) in/s', $sPage, $aMatch) > 0)
                {
                        $this -> m_aProgressInfo ['DueDate'] = strtotime ($aMatch [1]);
                }
                
                $this -> saveProgressInfo ();
                
                echo 'Milestone stored.';
                return LVPCommand :: OUTPUT_SUCCESS;
        }
}
?>