<?php

declare(strict_types=1);

namespace teamones\thinkorm;

use Throwable;
use think\db\BaseQuery;
use think\db\ConnectionInterface;
use think\db\Query;

/**
 * 带连接池的 DbManager。
 *
 * 仅在同时满足以下条件时启用池化，否则完全退化为父类行为：
 *  - 运行环境存在 Webman\Context 与 Workerman\Coroutine\Pool
 *  - 该连接配置里显式提供了 pool 段
 *
 * @mixin BaseQuery
 * @mixin Query
 */
class DbManager extends \think\DbManager
{
    private const HEARTBEAT_SQL_DEFAULT = 'SELECT 1 AS ping';
    private const HEARTBEAT_SQL_ORACLE = 'SELECT 1 FROM DUAL';

    /**
     * @var array<string, object>
     */
    protected static array $pools = [];

    /**
     * @param array|string|null $name
     * @param bool $force
     * @return ConnectionInterface
     * @throws Throwable
     */
    protected function instance(array|string|null $name = null, bool $force = false): ConnectionInterface
    {
        if (empty($name)) {
            $name = $this->getConfig('default', 'mysql');
        }

        if (!self::poolSupported($name, (array) $this->config)) {
            return parent::instance($name, $force);
        }

        $context = 'Webman\Context';
        $key = "think-orm.connections.$name";
        $connection = $context::get($key);
        if (!$connection) {
            if (!isset(static::$pools[$name])) {
                $poolClass = 'Workerman\Coroutine\Pool';
                $poolConfig = $this->config['connections'][$name]['pool'] ?? [];
                $pool = new $poolClass($poolConfig['max_connections'] ?? 10, $poolConfig);
                $pool->setConnectionCreator(function () use ($name) {
                    return $this->createConnection($name);
                });
                $pool->setConnectionCloser(function ($connection) use ($name) {
                    $this->closeConnection($connection, $name);
                });
                $pool->setHeartbeatChecker(function ($connection) {
                    $this->heartbeat($connection);
                });
                static::$pools[$name] = $pool;
            }
            try {
                $connection = static::$pools[$name]->get();
                $context::set($key, $connection);
            } finally {
                $context::onDestroy(function () use ($connection, $name) {
                    try {
                        $connection && static::$pools[$name]->put($connection);
                    } catch (Throwable) {
                        // ignore
                    }
                });
            }
        }

        return $connection;
    }

    /**
     * 是否具备启用连接池的条件。
     *
     * @param string $name
     * @param array $config
     * @return bool
     */
    public static function poolSupported(string $name, array $config): bool
    {
        if (!class_exists('Webman\Context') || !class_exists('Workerman\Coroutine\Pool')) {
            return false;
        }
        return !empty($config['connections'][$name]['pool']);
    }

    /**
     * 池化连接的心跳检测。
     *
     * @param mixed $connection
     * @return void
     */
    private function heartbeat(mixed $connection): void
    {
        $type = strtolower((string) $connection->getConfig('type'));

        if ($type === 'mongo' && class_exists('MongoDB\Driver\Command')) {
            $commandClass = 'MongoDB\Driver\Command';
            $connection->command(new $commandClass(['ping' => 1]));
            return;
        }

        $connection->query($type === 'oracle' || $type === 'oci' ? self::HEARTBEAT_SQL_ORACLE : self::HEARTBEAT_SQL_DEFAULT);
    }

    /**
     * @param ConnectionInterface $connection
     * @param string|null $name
     * @return void
     */
    protected function closeConnection(ConnectionInterface $connection, ?string $name = null): void
    {
        $connection->close();
        $clearProperties = function () {
            $this->db = null;
            $this->cache = null;
            $this->builder = null;
        };
        $clearProperties->call($connection);

        if ($name !== null && class_exists('Webman\Context')) {
            $context = 'Webman\Context';
            $context::set("think-orm.connections.$name", null);
        }
    }
}
