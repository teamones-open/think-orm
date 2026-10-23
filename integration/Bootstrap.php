<?php

declare(strict_types=1);

namespace teamones\thinkorm;

use think\facade\Db;
use think\Paginator;

/**
 * ThinkORM 在 webman 2.x 下的启动引导。
 *
 * 职责：
 *  1. 合并 thinkorm / think-orm 两套配置键（兼容旧项目配置文件名）
 *  2. 把容器中的 think\DbManager 指向本包的 DbManager（连接池）
 *  3. 写入数据库配置与查询缓存
 *  4. 注册分页的当前页 / 当前路径解析器
 */
class Bootstrap implements \Webman\Bootstrap
{
    private static bool $initialized = false;

    /**
     * @param \Workerman\Worker|null $worker
     * @return void
     */
    public static function start($worker): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        $config = array_replace_recursive(
            (array) config('thinkorm', []),
            (array) config('think-orm', [])
        );
        if (!$config) {
            return;
        }

        if (class_exists(\think\Container::class)) {
            \think\Container::getInstance()->bind(\think\DbManager::class, DbManager::class);
        }

        Db::setConfig($config);

        if (ThinkCache::isAvailable()) {
            Db::setCache(new ThinkCache());
        }

        Paginator::currentPageResolver(function ($pageName = 'page') {
            $request = function_exists('request') ? request() : null;
            if (!$request) {
                return 1;
            }
            $page = $request->input($pageName, 1);
            if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
                return (int) $page;
            }
            return 1;
        });

        Paginator::currentPathResolver(function () {
            $request = function_exists('request') ? request() : null;
            return $request ? $request->path() : '/';
        });

        $paginator = $config['paginator'] ?? '';
        if (!empty($paginator) && class_exists($paginator)) {
            Paginator::maker(function ($items, $listRows, $currentPage, $total, $simple, $options) use ($paginator) {
                return new $paginator($items, $listRows, $currentPage, $total, $simple, $options);
            });
        }
    }
}
