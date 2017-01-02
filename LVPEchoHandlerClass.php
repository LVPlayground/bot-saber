<?php
/**
 * LVPEchoHandler module for Nuwani v2
 *
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
class LVPEchoHandlerClass {

	/**
	 * A reference to the module this class resides in.
	 *
	 * @var LVPEchoHandler
	 */
	protected $m_pModule;

	/**
	 * @var LVPConfiguration
	 */
	public $Configuration;

	/**
	 * @var LVPDatabase
	 */
	public $Database;

	/**
	 * @var LVPCrewHandler
	 */
	public $CrewService;

	/**
	 * @var LVPEchoMessageParser
	 */
	public $EchoMessageParser;

	/**
	 * @var LVPCommandHandler
	 */
	public $CommandService;

	/**
	 * @var LVPIpManager
	 */
	public $IpService;

	/**
	 * @var LVPPlayerManager
	 */
	public $PlayerService;

	/**
	 * @var LVPWelcomeMessage
	 */
	public $WelcomeMessageService;

	/**
	 * @var LVPRadioHandler
	 */
	public $RadioService;

	/**
	 * @var LVPIrcService
	 */
	public $IrcService;

	/**
	 * The constructor will store references to the several services available
	 * for data processing.
	 *
	 * @param LVPEchoHandler $module The LVPEchoHandler module we're residing in.
	 */
	public function __construct(LVPEchoHandler $module) {
		$this->Configuration = $module->Configuration;
		$this->Database = $module->Database;
		$this->CrewService = $module->CrewService;
		$this->EchoMessageParser = $module->EchoMessageParser;
		$this->CommandService = $module->CommandService;
		$this->IpService = $module->IpService;
		$this->PlayerService = $module->PlayerService;
		$this->WelcomeMessageService = $module->WelcomeMessageService;
		$this->RadioService = $module->RadioService;
		$this->IrcService = $module->IrcService;
	}
}
