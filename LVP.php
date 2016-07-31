<?php
/**
 * LVPEchoHandler module for Nuwani v2
 *
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVP
{

        /**
         * These constants indicates the various levels available on LVP.
         * The VIP level is actually a separate switch in the LVP database, and
         * thus not actually a level. But seeing there's a separate VIP channel,
         * and I just use these to differentiate between the channels, I put VIP
         * after NONE.
         *
         * @var integer
         */
        const   LEVEL_NONE                      = 0;
        const   LEVEL_VIP                       = 1;
        const   LEVEL_DEVELOPER                 = 2;
        const   LEVEL_ADMINISTRATOR             = 3;
        const   LEVEL_MANAGEMENT                = 4;

        /**
         * Since LVP is only active on one IRC network, and since the bot is
         * active on other networks as well, we can filter out the messages from
         * other networks.
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
        const   RADIO_CHANNEL                   = '#lvp.radio';

        /**
         * It's all about the gameserver. So we're going to need the IP and port
         * of where the server runs without a doubt.
         *
         * @var string|integer
         */
        const   GAMESERVER_IP                   = 'play.sa-mp.nl';
        const   GAMESERVER_PORT                 = 7777;
}
