<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace PredisPHPRedis;

use Predis\Client;
use Predis\Command\CommandInterface;
use Predis\Connection\AbstractConnection;
use Predis\Connection\Parameters;
use Predis\Connection\ParametersInterface;

class PHPRedisConnection extends AbstractConnection {
	/** @var \Redis|\RedisCluster */
	private $redis;

	private $currentResponse;

	public function __construct($redis) {
		parent::__construct(new Parameters());
		$this->redis = $redis;
	}

	protected function assertParameters(ParametersInterface $parameters) {
	}

	protected function createResource() {
	}

	public function writeRequest(CommandInterface $command) {
		$this->currentResponse = call_user_func_array([$this->redis, 'rawCommand'], array_merge([$command->getId()], $command->getArguments()));
	}

	public function read() {
		return $this->currentResponse;
	}

	/**
	 * Wrap a php-redis instance in a predis instance
	 *
	 * @param \Redis|\RedisCluster $redis
	 * @return Client
	 */
	public static function wrap($redis): Client {
		return new Client('tcp://127.0.0.1', [
			'connections' => [
				'tcp' => function () use ($redis) {
					return new \PredisPHPRedis\PHPRedisConnection($redis);
				},
			]
		]);
	}
}
