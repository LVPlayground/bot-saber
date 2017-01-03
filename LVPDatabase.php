<?php
require 'LVPAsyncQuery.php';

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
	 * @var LVPAsyncQuery
	 */
	private $currentAsyncQuery;

	/**
	 * Queue of async queries we need to execute after the current one is done.
	 * @var \SqlQueue
	 */
	private $pendingAsyncQueries;

	/**
	 * The constructor will create a new connection with the configured
	 * connection details.
	 */
	public function __construct(LVPIrcService $ircService) {
		$this->IrcService = $ircService;
		$this->pendingAsyncQueries = new \SplQueue();

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
	 * Check if there are running async queries and if, check on their status.
	 */
	public function pollAsyncQuery() {
		if ($this->currentAsyncQuery == null) {
			if ($this->pendingAsyncQueries->isEmpty()) {
				// Nothing to do.
				return;
			}

			$this->currentAsyncQuery = $this->pendingAsyncQueries->dequeue();
			$this->currentAsyncQuery->start();
			if (!$this->query($this->currentAsyncQuery->getQuery(), MYSQLI_ASYNC)) {
				$this->currentAsyncQuery->error($this->error);
				// Error'd out before we even began, cya later.
				return;
			}
		}

		$links = array($this->connection);
		$read = $error = $reject = array();
		foreach ($links as $link) {
			$read[] = $error[] = $reject[] = $link;
		}

		if (!mysqli::poll($read, $error, $reject, 0)) {
			// Nothing to read yet.
			return;
		}

		foreach ($read as $link) {
			if ($result = $link->reap_async_query()) {
				if (is_object($result)) {
					// Only applies to SELECT queries
					$this->currentAsyncQuery->success($result);
					// print_r($result->fetch_row());
					// $result->free_result();
				} else {
					// INSERT/UPDATE/DELETE don't return a result
					$this->currentAsyncQuery->success($link);
				}
			} else {
				$this->IrcService->error(null, LVP::DEBUG_CHANNEL, 'Error: ' . $link->error);
				$this->currentAsyncQuery->error($link->error);
			}
		}
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
		
		if ($result == false) {
			$this->IrcService->error(null, LVP::DEBUG_CHANNEL, 'Executing query failed: ' . $this->error);
			$this->IrcService->error(null, LVP::DEBUG_CHANNEL, $unwanted);
		}
		
		return $result;
	}

	/**
	 * Execute a query asynchronously, returning a promise.
	 * 
	 * @param  string $query The query to execute.
	 * @return React\Promise\Promise
	 */
	public function queryAsync($query) {
		$asyncQuery = new LVPAsyncQuery($query);
		$this->pendingAsyncQueries->enqueue($asyncQuery);

		return $asyncQuery->promise()->then(function () {
			$this->currentAsyncQuery = null;
		}, function () {
			$this->currentAsyncQuery = null;
		});
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
