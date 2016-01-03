<?php
/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * @author Dik Grapendaal <dik.grapendaal@gmail.com>
 * 
 * $Id: LVP.php 367 2014-01-11 18:49:01Z Dik $
 */
class LVP
{
        
        /**
         * These constants indicates the various levels available on LVP. 
         * The VIP level is actually a separate switch in the LVP database, and
         * thus not actually a level. But seeing there's a separate VIP channel,
         * and I just use these to differentiate between the channels, I put VIP
         * above NONE.
         * 
         * @var integer
         */
        const   LEVEL_NONE                      = 0;
        const   LEVEL_VIP                       = 1;
        const   LEVEL_DEVELOPER                 = 2;
        const   LEVEL_MODERATOR                 = 3;
        const   LEVEL_ADMINISTRATOR             = 4;
        const   LEVEL_MANAGEMENT                = 5;
        
        /**
         * Since LVP is only active on one IRC network, and my bot is likely to
         * be put to use on other networks as well in the future, we can filter
         * the messages from other networks as well. This to prevent misuse or
         * hacking attempts.
         * 
         * @var string
         */
        const   NETWORK                         = 'GTANet';
        
        /**
         * These constants define some key channels in the LVP imperium. Keep in
         * mind that these are always in lower case, for best results.
         * 
         * @var string
         */
        const   ECHO_CHANNEL                    = '#lvp.echo';
        const   CREW_CHANNEL                    = '#lvp.crew';
        const   MANAGEMENT_CHANNEL              = '#lvp.managers';
        const   DEBUG_CHANNEL                   = '#bot';
        
        /**
         * It's all about the gameserver. So we're going to need the IP and port
         * of where the server runs without a doubt. So how could I forget this,
         * even after 2 weeks of developing? I feel stupid.
         * 
         * @var string|integer
         */
        const   GAMESERVER_IP                   = 'play.sa-mp.nl';
        const   GAMESERVER_PORT                 = 7777;
        
        /**
         * In order to do something with the LVP Trac, we need to know where to
         * look for it and the credentials to login with. These are defined
         * below.
         * 
         * @var string
         */
        const   TRAC_BASE_URL                   = 'http://trac.sa-mp.nl/lvp';
        const   TRAC_USERNAME                   = '';
        const   TRAC_PASSWORD                   = '';
}
?>