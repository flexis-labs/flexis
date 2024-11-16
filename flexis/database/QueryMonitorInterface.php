<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

/**
 * Интерфейс, определяющий мониторинг запросов.
 */
interface QueryMonitorInterface {
    /**
     * Действие в соответствии с запущенным запросом.
     *
     * @param   string         $sql          SQL, который будет выполнен.
     * @param   object[]|null  $boundParams  Список связанных параметров, используемых в запросе.
     *                                       Каждый элемент представляет собой объект, который содержит: значение, тип данных.
     *
     * @return  void
     */
    public function startQuery(string $sql, ?array $boundParams = null): void;

    /**
     * Действие при остановленном запросе.
     *
     * @return  void
     */
    public function stopQuery(): void;
}
