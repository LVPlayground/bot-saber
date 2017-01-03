<?php
use React\Promise\Deferred;

/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPAsyncQuery {

	/**
	 * @var string
	 */
	private $query;

	/**
	 * @var integer
	 */
	private $createTime;

	/**
	 * @var integer
	 */
	private $startTime;

	/**
	 * @var integer
	 */
	private $finishTime;

	/**
	 * @var React\Promise\Deferred
	 */
	private $deferred;

	public function __construct($query) {
		$this->query = $query;
		$this->createTime = time();
		$this->deferred = new Deferred();
	}

	public function getQuery() {
		return $this->query;
	}

	public function promise() {
		return $this->deferred->promise();
	}

	public function start() {
		$this->startTime = time();
	}

	public function error($error) {
		$this->finishTime = time();
		$this->deferred->reject($error);
	}

	public function success($result) {
		$this->finishTime = time();
		$this->deferred->resolve($result);
	}
}