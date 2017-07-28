<?php

namespace PredisPHPRedis;

use Predis\Client;

class Wrapper {
	/**
	 * Wrap a php-redis instance in a predis instance
	 *
	 * @param \Redis|\RedisCluster $redis
	 * @param array $options any options to pass to predis
	 * @return Client
	 */
	public static function wrap($redis, $options = []) {
		$options = array_merge(is_array($options) ? $options : [], [
			'connections' => [
				'tcp' => function () use ($redis) {
					return new \PredisPHPRedis\PHPRedisConnection($redis);
				},
			]
		]);
		return new Client('tcp://127.0.0.1', $options);
	}
}
