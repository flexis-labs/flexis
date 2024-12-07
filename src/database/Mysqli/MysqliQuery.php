<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Mysqli;

use Flexis\Database\DatabaseQuery;
use Flexis\Database\Query\MysqlQueryBuilder;

/**
 * Класс построения запросов MySQLi.
 */
class MysqliQuery extends DatabaseQuery {
    use MysqlQueryBuilder;

    /**
     * Список нулевых или нулевых представлений даты и времени.
     *
     * @var    array
     */
    protected array $nullDatetimeList = ['0000-00-00 00:00:00', '1000-01-01 00:00:00'];
}
