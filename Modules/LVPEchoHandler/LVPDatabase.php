<?php
use Nuwani \ ModuleManager;

/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * This class makes a connection to the LVP database on the webserver. I use
 * a separate class not just because I felt like it, but also because the main
 * database connection of the bot this class is running in is already taken by
 * another connection. Also, by providing my own database Singleton class,
 * extending it with some more redundancy, security or whatever buzzwords you
 * can think of, will be possible in the future without touching Nuwani's
 * source. I copied the base from Nuwani's Database class and modified it a bit
 * for some specific functionality.
 * 
 * @author Peter Beverloo <peter@lvp-media.com>
 * @author Dik Grapendaal <dik.grapendaal@gmail.com>
 * 
 * $Id: LVPDatabase.php 296 2012-11-18 22:23:32Z Dik $
 */
class LVPDatabase extends MySQLi
{
        /**
         * This property contains the instance of the active MySQLi instance.
         * By utilizing Singleton here we avoid having MySQL connections for
         * every single requests, but rather just when they're needed.
         * 
         * @var LVPDatabase
         */
        private static $s_pInstance;
        
        /**
         * This property indicates when the current connection has to
         * be killed, and restarted to clear up buffers and all.
         * 
         * @var integer
         */
        private static $s_nRestartTime;
        
        /**
         * The constructor will create a new connection with the predefined
         * connection details. It's private because of the Singleton pattern.
         */
        public function __construct ()
        {
                $aConfiguration = Configuration :: getInstance () -> get ('LVPDatabase');

                parent::__construct(
                        $aConfiguration ['hostname'],
                        $aConfiguration ['username'],
                        $aConfiguration ['password'],
                        $aConfiguration ['database']
                );
        }
        
        /**
         * Creates a new connection with the database or returns the active
         * one if there is one, so no double connections for anyone.
         * 
         * @return LVPDatabase
         */
        public static function getInstance ()
        {
                if (self :: $s_pInstance == null || self :: $s_nRestartTime < time ())
                {
                        self :: restart ();
                        self :: $s_nRestartTime = time () + 86400;
                        
                        self :: $s_pInstance = new self ();
                }
                
                return self :: $s_pInstance;
        }
        
        /**
         * I've overridden the ping method so that it reconnects using our
         * connection details and stores the instance in the right property.
         */
        public function ping ()
        {
                if (!parent :: ping ())
                {
                        self :: $s_pInstance = new self ();
                }
        }
        
        /**
         * To prevent random errors being thrown to the users, I override this
         * method and catch the error as-it-happens, to send it to the debug
         * channel afterwards. The behaviour of the method is not changed.
         * 
         * @param string $sStatement The statement to prepare.
         * @return MySQLi_STMT
         */
        public function prepare ($sStatement)
        {
                $pStatement = parent :: prepare ($sStatement);
                if (!is_object ($pStatement))
                {
                        /** Oh fuck, log the error. **/
                        ModuleManager :: getInstance () -> offsetGet ('LVPEchoHandler') ->
                                error (null, LVP :: DEBUG_CHANNEL, 'Preparing statement failed: ' . $this -> error);
                        
                        return false;
                }
                
                return $pStatement;
        }
        
        /**
         * Practically the same as the prepare () method, we're catching the error
         * and sending it to the debug channel.
         * 
         * @param string $sQuery The query to execute.
         * @param integer $nResultMode The way you want to receive the result.
         * @return mixed
         */
        public function query ($sQuery, $nResultMode = MYSQLI_STORE_RESULT)
        {
                ob_start ();
                $mResult = parent :: query ($sQuery, $nResultMode);
                $sUnwanted = ob_get_clean ();
                
                if ($mResult == false)
                {
                        /** Yet another error, sigh. **/
                        ModuleManager :: getInstance () -> offsetGet ('LVPEchoHandler') ->
                                error (null, LVP :: DEBUG_CHANNEL, 'Executing query failed: ' . $this -> error);
                        ModuleManager :: getInstance () -> offsetGet ('LVPEchoHandler') ->
                                error (null, LVP :: DEBUG_CHANNEL, $sUnwanted);
                }
                
                return $mResult;
        }
        
        /**
         * This method will forcibly close the connection of the database, so
         * that a new connection will be made when getInstance () is called.
         */
        public static function restart ()
        {
                self :: $s_pInstance = null; // Force it to close.
        }
}
?>
