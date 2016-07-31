<?php
/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPEchoHandlerClass
{
        
        /**
         * A pointer to the module this class resides in.
         * 
         * @var LVPEchoHandler
         */
        protected $m_pModule;
        
        /**
         * The constructor will store a pointer to the LVPEchoHandler module for
         * use while the extending class does its job.
         * 
         * @param LVPEchoHandler $pEchoHandler The LVPEchoHandler module we're residing in.
         */
        public function __construct (LVPEchoHandler $pEchoHandler)
        {
                $this -> m_pModule = $pEchoHandler;
        }
}