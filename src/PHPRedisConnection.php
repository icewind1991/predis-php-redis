<?php

namespace PredisPHPRedis;

use Predis\Client;
use Predis\Command\CommandInterface;
use Predis\Connection\AbstractConnection;
use Predis\Connection\Parameters;
use Predis\Connection\ParametersInterface;
use Predis\Response\Error;
use Predis\Response\Status;

class PHPRedisConnection extends AbstractConnection {
	/** @var \Redis|\RedisCluster */
	private $redis;

	/**
	 * @var array
	 */
	private $currentResponses;

	/** @var CommandInterface */
	private $lastCommand;

	private $inQueue = false;

	private $queuedCommands = [];

	const TYPEMAP = [
		\Redis::REDIS_STRING => 'string',
		\Redis::REDIS_LIST => 'list',
		\Redis::REDIS_SET => 'set',
		\Redis::REDIS_ZSET => 'zset',
		\Redis::REDIS_HASH => 'hash',
		\Redis::REDIS_NOT_FOUND => 'none',
	];

	public function __construct($redis) {
		parent::__construct(new Parameters());
		$this->redis = $redis;
	}

	protected function assertParameters(ParametersInterface $parameters) {
	}

	protected function createResource() {
	}

	public function writeRequest(CommandInterface $command) {
		$this->lastCommand = $command;

		if ($command->getId() === 'PEXPIREAT' || $command->getId() === 'MOVE') {
			$command->setArguments([$command->getArgument(0), (int)$command->getArgument(1)]);
		}


		if ($command->getId() === 'EXEC') {
			$this->inQueue = false;
		}
		if ($command->getId() === 'DISCARD') {
			$this->inQueue = false;
			$this->queuedCommands = [];
		}

		if ($this->inQueue) {
			$this->queuedCommands[] = $command;
		}

		if ($command->getId() === 'MULTI') {
			$this->inQueue = true;
		}

		if ($command->getId() === 'TYPE') {
			$type = $this->redis->type($command->getArgument(0));
			$this->currentResponses[] = self::TYPEMAP[$type];
		} else {
			$this->currentResponses[] = call_user_func_array([$this->redis, 'rawCommand'], array_merge([$command->getId()], $command->getArguments()));
		}
	}

	public function read() {
		$response = array_shift($this->currentResponses);
		return $this->handleResponse($response, $this->lastCommand, $this->queuedCommands);
	}

	private function handleResponse($response, CommandInterface $command, array $subCommands = []) {
		if (strpos($command->getId(), 'FLOAT') !== false || strpos($command->getId(), 'GETALL') !== false) {
			$response = $this->round($response);
		}
		if ($response === true) {
			return $this->translateTrue($command);
		} else if ($response === false) {
			$error = $this->getLastError();
			if ($error) {
				return new Error($this->getLastError());
			}
		} else if (is_array($response)) {
			if ($command->getId() === 'EXEC' && count($response) === 0 && count($subCommands) > 0) {
				return null;
			}
			return $this->translateArray($response, $command, $subCommands);
		} else {
			if ($command->getId() === 'EXEC') {
				$this->queuedCommands = [];
			}
			return $response;
		}
	}

	private function round($value) {
		if (is_numeric($value) && is_string($value)) {
			return '' . round($value, 17);
		} else {
			return $value;
		}
	}

	private function translateArray(array $response, CommandInterface $command, array $subCommands = []) {
		return array_map(function ($item) use ($command, &$subCommands) {
			if (is_string($item) && $command->getId() === 'INFO') {
				return Status::get($item);
			}

			$activeCommand = count($subCommands) ? array_shift($subCommands) : $command;
			$activeSubCommands = count($subCommands) ? [] : $subCommands;

			return $this->handleResponse($item, $activeCommand, $activeSubCommands);
		}, $response);
	}

	private function translateTrue(CommandInterface $command) {
		if ($this->inQueue && $command->getId() !== 'MULTI') {
			return Status::get('QUEUED');
		}

		switch ($command->getId()) {
			case 'PING':
				return Status::get('PONG');
			case 'MIGRATE':
				return Status::get('NOKEY');
			case 'EXISTS':
				return 1;
			default:
				return Status::get('OK');
		}
	}

	/**
	 * Get the last error and do some translation
	 *
	 * @return string
	 */
	private function getLastError() {
		$sourceError = $this->redis->getLastError();
		switch ($sourceError) {
			case 'ERR DB index is out of range':
				return 'ERR invalid DB index';
			default:
				return $sourceError;
		}
	}
}
