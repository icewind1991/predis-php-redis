# predis-php-redis

Compatibility layer between [`predis`](https://github.com/nrk/predis) and [`php-redis`](https://github.com/phpredis/phpredis)

## Usage

```php
// connect using php-redis as normal
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);

// create a predis instance using the existing php-redis instance
$predis = \PredisPHPRedis\Wrapper::wrap($redis);

// use predis as normal

$predis->set('foo', 'bar');
```

## Why

This project is not intended for new projects that want to use `predis` (just use plain `predis`, possibly with `phpiredis`),
but instead for projects that are already using `php-redis` that want to integrate code relying on `predis` or the other way around.

## Compatibility

Beside the items listed below, this adapter is tested against the full predis test suite and thus should be
fully compatible with "normal" predis behaviour. Any behaviour that is not covered by the predis test suite might
behave unexpected though.

## Known issues and limitations

- When unsubscribing from multiple channels, only the first channel name is returned correctly.
- Predis builtin clustering support is untested and probably not working, instead it's recommended to handle the clustering in php-redis
