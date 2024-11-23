<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Pgsql;

use Flexis\Database\Pdo\PdoQuery;
use Flexis\Database\Query\PostgresqlQueryBuilder;
use Flexis\Database\Query\QueryElement;

/**
 * Класс построения запросов PDO PostgreSQL.
 *
 * @property-read  QueryElement  $forUpdate  Элемент FOR UPDATE, используемый в блокировке FOR UPDATE.
 * @property-read  QueryElement  $forShare   Элемент FOR SHARE, используемый в замке FOR SHARE.
 * @property-read  QueryElement  $noWait     Элемент NOWAIT, используемый в блокировках FOR SHARE и FOR UPDATE.
 * @property-read  QueryElement  $returning  Возвращающийся элемент INSERT INTO.
 */
class PgsqlQuery extends PdoQuery {
    use PostgresqlQueryBuilder;

    /**
     * Список нулевых или нулевых представлений даты и времени.
     *
     * @var    array
     */
    protected array $nullDatetimeList = ['1970-01-01 00:00:00'];

    /**
     * Приводит значение к символу.
     *
     * Перед передачей методу убедитесь, что значение заключено в правильные кавычки.
     *
     * <pre>
     * Использование:
     * $query->select($query->castAsChar('a'));
     * $query->select($query->castAsChar('a', 40));
     * </pre>
     *
     * @param   string  $value   Значение для преобразования в виде символа.
     * @param   ?int    $len     Длина символа.
     *
     * @return  string  Возвращает приведенное значение.
     */
    public function castAsChar(string $value, ?int $len = null): string {
        if ((int) $len < 1) {
            return $value . '::text';
        }

        return 'CAST(' . $value . ' AS CHAR(' . $len . '))';
    }
}
