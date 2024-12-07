<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Mysql;

use Flexis\Database\Pdo\PdoQuery;
use Flexis\Database\Query\MysqlQueryBuilder;

/**
 * Класс построения запросов MySQL.
 */
class MysqlQuery extends PdoQuery {
    use MysqlQueryBuilder;

    /**
     * Список нулевых или нулевых представлений даты и времени.
     *
     * @var    array
     */
    protected array $nullDatetimeList = ['0000-00-00 00:00:00', '1000-01-01 00:00:00'];
}
