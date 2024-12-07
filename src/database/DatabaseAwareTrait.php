<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

use Flexis\Database\Exception\DatabaseNotFoundException;

/**
 * Определяет признак класса поддержки баз данных.
 */
trait DatabaseAwareTrait {
    /**
     * База данных.
     *
     * @var    DatabaseInterface
     */
    private DatabaseInterface $databaseAwareTraitDatabase;

    /**
     * Возвращает базу данных.
     *
     * @return  DatabaseInterface
     *
     * @throws  DatabaseNotFoundException Может быть выброшено, если база данных не установлена.
     */
    protected function getDatabase(): DatabaseInterface {
        return $this->databaseAwareTraitDatabase;
    }

    /**
     * Устанавливает базу данных.
     *
     * @param   DatabaseInterface  $db  База данных.
     *
     * @return  void
     *
     */
    public function setDatabase(DatabaseInterface $db): void {
        $this->databaseAwareTraitDatabase = $db;
    }
}
