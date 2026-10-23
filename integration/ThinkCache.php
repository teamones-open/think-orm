<?php

declare(strict_types=1);

namespace teamones\thinkorm;

use DateInterval;
use DateTimeInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * 把 webman 的缓存门面适配成 PSR-16，供 ThinkORM 的查询缓存使用。
 *
 * 支持 support\Cache（webman/cache 或框架自带）与 support\think\Cache。
 */
class ThinkCache implements CacheInterface
{
    /**
     * @var string|null
     */
    private ?string $store;

    public function __construct(?string $store = null)
    {
        $this->store = $store ?: self::detect();
    }

    /**
     * 探测可用的缓存门面。
     *
     * @return string|null
     */
    public static function detect(): ?string
    {
        foreach (['support\Cache', 'support\think\Cache'] as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return self::detect() !== null;
    }

    /**
     * @param string $method
     * @param array $args
     * @return mixed
     */
    private function call(string $method, array $args)
    {
        if ($this->store === null) {
            return null;
        }
        $store = $this->store;
        return $store::$method(...$args);
    }

    public function get($key, mixed $default = null): mixed
    {
        return $this->call('get', [$key, $default]);
    }

    public function set($key, $value, $ttl = null): bool
    {
        return (bool) $this->call('set', [$key, $value, $ttl]);
    }

    public function delete($key): bool
    {
        return (bool) $this->call('delete', [$key]);
    }

    public function clear(): bool
    {
        return (bool) $this->call('clear', []);
    }

    public function getMultiple($keys, $default = null): iterable
    {
        return (array) $this->call('getMultiple', [$keys, $default]);
    }

    public function setMultiple($values, $ttl = null): bool
    {
        return (bool) $this->call('setMultiple', [$values, $ttl]);
    }

    public function deleteMultiple($keys): bool
    {
        return (bool) $this->call('deleteMultiple', [$keys]);
    }

    public function has($key): bool
    {
        return (bool) $this->call('has', [$key]);
    }

    /**
     * ThinkORM 懒写入字段自增。
     */
    public function inc(string $name, float|int $step = 1): mixed
    {
        return $this->call('inc', [$name, $step]);
    }

    /**
     * ThinkORM 懒写入字段自减。
     */
    public function dec(string $name, float|int $step = 1): mixed
    {
        return $this->call('dec', [$name, $step]);
    }
}
