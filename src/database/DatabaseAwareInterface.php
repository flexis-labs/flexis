<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

/**
 * Определяет интерфейс для класса, поддерживающего DatabaseInterface.
 */
interface DatabaseAwareInterface {
    /**
     * Устанавливает базу данных.
     *
     * @param   DatabaseInterface  $db  База данных.
     *
     * @return  void
     *
     */
    public function setDatabase(DatabaseInterface $db): void;
}
