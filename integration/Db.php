<?php

declare(strict_types=1);

namespace teamones\thinkorm;

use think\db\BaseQuery;
use think\Facade;

/**
 * 绑定到本包 DbManager 的门面，等价于 think\facade\Db。
 *
 * @mixin \think\DbManager
 * @mixin BaseQuery
 */
class Db extends Facade
{
    protected static function getFacadeClass(): string
    {
        return DbManager::class;
    }
}
