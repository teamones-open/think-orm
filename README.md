# teamones/think-orm 3.x

基于 [topthink/think-orm](https://github.com/top-think/think-orm) **4.0.51** 的 webman 2.x 适配版。

## 版本对应

| 版本 | PHP | 上游基线 | 说明 |
| --- | --- | --- | --- |
| 3.x | >= 8.1（兼容 8.5） | think-orm 4.0.51 | webman 2.2 / workerman 5.2 适配 |
| 0.0.x | >= 7.1 | think-orm 2.0.53 | 旧 webman 1.x 使用，仅缺陷修复 |

## 与上游的差异

1. **保留 teamones 扩展**：`BaseQuery::hint()`、`BaseQuery::forceMasterDB()`，配合 MySQL builder 的 `%HINT%` 注入 SQL 注释（供 DB 代理做读写分离/强制主库）。
2. **新增 webman 2.2 集成层**（`teamones\thinkorm` 命名空间）：
   - `Bootstrap`：合并 `config('thinkorm')` 与 `config('think-orm')`，绑定 `think\DbManager`，写入数据库配置、查询缓存与分页解析器。
   - `DbManager`：连接池（需 `Webman\Context` + `Workerman\Coroutine\Pool` 且连接配置显式提供 `pool` 段，否则退化为原生行为）。
   - `ThinkCache`：把 `support\Cache` 适配成 PSR-16。
   - `Db`：绑定本包 DbManager 的门面。
3. **依赖收紧**：`psr/log ^2.0 || ^3.0`（上游为 `>=1.0`），可与 webman 2.2 的 `psr/log ^2||^3` 共存。
4. 通过 `replace: {"topthink/think-orm": "4.0.51"}` 顶替上游包，避免同命名空间冲突。

## 安装

```bash
composer require teamones/think-orm:^3.0
```

## webman 2.2 配置

`config/bootstrap.php` 增加：

```php
return [
    teamones\thinkorm\Bootstrap::class,
];
```

配置文件继续使用 `config/thinkorm.php`（旧键名）或改为 `config/think-orm.php`（新键名），两者会被合并，`think-orm` 优先。

连接池示例：

```php
'connections' => [
    'mysql' => [
        // ...
        'pool' => [
            'max_connections' => 5,
            'min_connections' => 1,
            'wait_timeout'    => 3,
            'idle_timeout'    => 60,
        ],
    ],
],
```

强制主库查询：

```php
Db::name('asset')->forceMasterDB()->select();
```
