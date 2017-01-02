<?php
use Nuwani\Configuration;
use Nuwani\ModuleManager;

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
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPDatabase extends MySQLi {

	/**
	 * The constructor will create a new connection with the configured
	 * connection details.
	 */
	public function __construct() {
		$aConfiguration = Configuration::getInstance()->get('LVPDatabase');

		parent::__construct(
			$aConfiguration['hostname'],
			$aConfiguration['username'],
			$aConfiguration['password'],
			$aConfiguration['database']
		);
	}
	
	/**
	 * To prevent random errors being thrown to the users, I override this
	 * method and catch the error as-it-happens, to send it to the debug
	 * channel afterwards. The behaviour of the method is not changed.
	 * 
	 * @param string $sStatement The statement to prepare.
	 * @return MySQLi_STMT
	 */
	public function prepare($sStatement) {
		$pStatement = parent::prepare($sStatement);
		if (!is_object($pStatement)) {
			ModuleManager::getInstance()->offsetGet('LVPEchoHandler')->
				error(null, LVP::DEBUG_CHANNEL, 'Preparing statement failed: ' . $this->error);
			
			return false;
		}
		
		return $pStatement;
	}
	
	/**
	 * Practically the same as the prepare() method, we're catching the error
	 * and sending it to the debug channel.
	 * 
	 * @param string $sQuery The query to execute.
	 * @param integer $nResultMode The way you want to receive the result.
	 * @return mixed
	 */
	public function query($sQuery, $nResultMode = MYSQLI_STORE_RESULT) {
		ob_start();
		$mResult = parent::query($sQuery, $nResultMode);
		$sUnwanted = ob_get_clean();
		
		if ($mResult == false) {
			ModuleManager::getInstance()->offsetGet('LVPEchoHandler')->
				error(null, LVP::DEBUG_CHANNEL, 'Executing query failed: ' . $this->error);
			ModuleManager::getInstance()->offsetGet('LVPEchoHandler')->
				error(null, LVP::DEBUG_CHANNEL, $sUnwanted);
		}
		
		return $mResult;
	}

	public function queryAsync($query) {
		// TODO Insert magic here
	}
}
