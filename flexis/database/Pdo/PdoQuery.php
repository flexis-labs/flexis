<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Pdo;

use Flexis\Database\DatabaseQuery;

/**
 * Класс построения запросов PDO.
 */
abstract class PdoQuery extends DatabaseQuery {
    /**
     * Список нулевых или нулевых представлений даты и времени.
     *
     * @var    array
     */
    protected array $nullDatetimeList = ['0000-00-00 00:00:00'];

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
     * @param   string  $value  Значение для преобразования в виде символа.
     * @param   ?int    $len    Длина символа.
     *
     * @return  string  Возвращает приведенное значение.
     */
    public function castAsChar(string $value, ?int $len = null): string {
        if (!$len) {
            return $value;
        } else {
            return 'CAST(' . $value . ' AS CHAR(' . $len . '))';
        }
    }
}
