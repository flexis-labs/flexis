<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Event;

use Flexis\Database\DatabaseInterface;
use Flexis\Event\Event;

/**
 * Событие подключения к базе данных
 */
class ConnectionEvent extends Event {
    /**
     * Объект DatabaseInterface для этого события
     *
     * @var    DatabaseInterface
     */
    private DatabaseInterface $driver;

    /**
     * Конструктор.
     *
     * @param   string             $name    Название события.
     * @param   DatabaseInterface  $driver  Объект DatabaseInterface для этого события.
     */
    public function __construct(string $name, DatabaseInterface $driver) {
        parent::__construct($name);

        $this->driver = $driver;
    }

    /**
     * Возвращает объект DatabaseInterface, прикрепленный к этому событию.
     *
     * @return  DatabaseInterface
     */
    public function getDriver(): DatabaseInterface {
        return $this->driver;
    }
}
