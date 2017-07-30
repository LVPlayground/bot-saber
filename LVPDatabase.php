<?php
use Nuwani\Configuration;

/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * This class makes a connection to the LVP database on the webserver. I use
 * a separate class not just because I felt like it, but also because the main
 * database connection of the bot this class is running in is already taken by
 * another connection.
 * 
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPDatabase {

	/**
	 * @var LVPIrcService
	 */
	private $IrcService;

	/**
	 * @var \MySqli
	 */
	private $connection;

	/**
	 * The constructor will create a new connection with the configured
	 * connection details.
	 */
	public function __construct(LVPIrcService $ircService) {
		$this->IrcService = $ircService;

		$this->connect();
	}

	public function connect() {
		$configuration = Configuration::getInstance()->get('LVPDatabase');
		$this->connection = new \MySQLi(
			$configuration['hostname'],
			$configuration['username'],
			$configuration['password'],
			$configuration['database']
		);
	}

	public function escape($escapeStr) {
		return $this->connection->real_escape_string($escapeStr);
	}

	/**
	 * To prevent random errors being thrown to the users, I override this
	 * method and catch the error as-it-happens, to send it to the debug
	 * channel afterwards. The behaviour of the method is not changed.
	 * 
	 * @param string $statement The statement to prepare.
	 * @return MySQLi_STMT
	 */
	public function prepare($statement) {
		$prepared = $this->connection->prepare($statement);
		if (!is_object($prepared)) {
			$this->IrcService->error(null, LVP::DEBUG_CHANNEL, 'Preparing statement failed: ' . $this->error);
			
			return false;
		}
		
		return $prepared;
	}
	
	/**
	 * Practically the same as the prepare() method, we're catching the error
	 * and sending it to the debug channel.
	 * 
	 * @param string $query The query to execute.
	 * @param integer $resultMode The way you want to receive the result.
	 * @return mixed
	 */
	public function query($query, $resultMode = MYSQLI_STORE_RESULT) {
		ob_start();
		$result = $this->connection->query($query, $resultMode);
		$unwanted = ob_get_clean();
		
		if (!$result) {
			$this->IrcService->error(null, LVP::DEBUG_CHANNEL, 'Executing query failed: ' . $this->error);
			$this->IrcService->error(null, LVP::DEBUG_CHANNEL, $unwanted);
		}
		
		return $result;
	}

	/**
	 * Pings the database connection to see if it's still alive. If not, it will
	 * reconnect.
	 */
	public function ping() {
		if (!$this->connection->ping()) {
			$this->restart();
		}
	}

	/**
	 * Restarts the database connection.
	 */
	public function restart() {
		$this->connection->close();
		$this->connect();
	}
}
