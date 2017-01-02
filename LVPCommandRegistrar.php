<?php
/**
 * LVPEchoHandler module for Nuwani v2
 * 
 * @author Dik Grapendaal <dik@sa-mp.nl>
 */
interface LVPCommandRegistrar {
	public function registerCommands(LVPCommandHandler $commandService);
}